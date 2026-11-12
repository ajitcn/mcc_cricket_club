<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

final class AdmissionPdfService
{
    private const PAGE_WIDTH = 595;
    private const PAGE_HEIGHT = 842;
    private const LEFT = 40;
    private const RIGHT = 40;
    private const TOP = 40;
    private const BOTTOM = 42;
    private const BLUE = [0.086, 0.231, 0.388];
    private const PALE_BLUE = [0.918, 0.941, 0.965];
    private const BORDER = [0.57, 0.65, 0.73];

    /** @var list<list<string>> */
    private array $pages = [];

    /** @var array<string, array{jpeg: string, width: int, height: int}> */
    private array $images = [];

    private float $cursorY = self::PAGE_HEIGHT - self::TOP;

    /** @param array<string, mixed> $admission */
    public function render(array $admission): string
    {
        $html = view('App\\Modules\\Admissions\\Views\\pdf', [
            'admission' => $admission,
        ]);

        return $this->convertHtmlToPdf($html);
    }

    /** @param array<string, mixed> $payment */
    public function renderPaymentReceipt(array $payment): string
    {
        $this->pages = [];
        $this->images = [];
        $this->startPage();

        $this->drawRect(self::LEFT, self::PAGE_HEIGHT - 105, self::PAGE_WIDTH - self::LEFT - self::RIGHT, 65, self::BLUE, self::BLUE);
        $logo = $this->registerImage(defined('ROOTPATH') ? ROOTPATH . 'public/assets/img/logo.png' : 'public/assets/img/logo.png');
        if ($logo !== null) {
            $this->drawImage($logo, self::LEFT + 10, self::PAGE_HEIGHT - 98, 48, 50);
        }
        $this->drawText('MUMBAI CRICKET CLUB', self::LEFT + 70, self::PAGE_HEIGHT - 65, 16, true, false, [1, 1, 1]);
        $this->drawText('Payment receipt', self::LEFT + 70, self::PAGE_HEIGHT - 84, 9, false, false, [0.9, 0.95, 1]);

        $fields = [
            ['Admission', (string) ($payment['admission_number'] ?? 'Not available')],
            ['Receipt number', (string) ($payment['receipt_number'] ?? 'Not provided')],
            ['Payment date', (string) ($payment['payment_date'] ?? 'Not available')],
            ['Payment type', ucfirst((string) ($payment['payment_type'] ?? 'Fee'))],
            ['Payment method', (string) ($payment['payment_method'] ?? 'Not provided')],
            ['Installment', (string) ($payment['year_label'] ?? '—') . ' - ' . (string) ($payment['installment_number'] ?? '—')],
            ['Branch', (string) ($payment['branch_name'] ?? 'Not available')],
            ['Reference', (string) ($payment['reference_number'] ?? 'Not provided')],
        ];
        $startY = self::PAGE_HEIGHT - 155;
        foreach ($fields as $index => [$label, $value]) {
            $column = $index % 2;
            $row = intdiv($index, 2);
            $x = self::LEFT + ($column === 0 ? 12 : 270);
            $y = $startY - ($row * 58);
            $this->drawText(strtoupper($label), $x, $y, 7, false, false, [0.38, 0.45, 0.56]);
            $this->drawText($value, $x, $y - 15, 10, false, false, [0.14, 0.21, 0.33]);
            $this->drawLine($x, $y - 27, $x + 235, $y - 27, [0.86, 0.89, 0.93]);
        }

        $this->drawRect(self::LEFT, 430, self::PAGE_WIDTH - self::LEFT - self::RIGHT, 68, self::PALE_BLUE, [0.82, 0.87, 0.94]);
        $this->drawText('AMOUNT RECEIVED', self::LEFT + 14, 475, 8, false, false, [0.38, 0.45, 0.56]);
        $this->drawText(number_format((float) ($payment['amount_received'] ?? 0), 2), self::LEFT + 14, 450, 16, true, false, self::BLUE);
        $this->drawText('Generated from the MCC Cricket Club portal.', self::LEFT, 395, 8, false, false, [0.4, 0.47, 0.57]);
        $this->finishPage();

        return $this->buildPdf();
    }

    private function convertHtmlToPdf(string $html): string
    {
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $body = $document->getElementsByTagName('body')->item(0);
        $this->pages = [];
        $this->images = [];
        $this->startPage();

        if ($body instanceof DOMElement) {
            foreach ($body->childNodes as $node) {
                $this->renderNode($node);
            }
        }

        $this->finishPage();

        return $this->buildPdf();
    }

    private function renderNode(DOMNode $node): void
    {
        if (! $node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->tagName);
        $class = (string) $node->getAttribute('class');

        if ($tag === 'header') {
            $this->renderHeader($node);
            return;
        }

        if ($tag === 'h1' && str_contains($class, 'pdf-title')) {
            $this->drawText($this->text($node), self::PAGE_WIDTH / 2, $this->cursorY - 15, 15, true, true);
            $this->cursorY -= 27;
            return;
        }

        if ($tag === 'main') {
            foreach ($node->childNodes as $child) {
                $this->renderNode($child);
            }
            return;
        }

        if ($tag === 'section' && str_contains($class, 'pdf-section')) {
            if (str_contains($class, 'pdf-terms') && $this->cursorY < self::PAGE_HEIGHT - self::TOP) {
                $this->finishPage();
                $this->startPage();
            }
            $this->renderSection($node);
        }
    }

    private function renderHeader(DOMElement $header): void
    {
        $this->ensureSpace(86);
        $this->drawRect(self::LEFT, $this->cursorY - 66, self::PAGE_WIDTH - self::LEFT - self::RIGHT, 66, [1, 1, 1], self::BLUE);
        $logo = $header->getElementsByTagName('img')->item(0);
        $logoName = $logo instanceof DOMElement ? $this->registerImage($logo->getAttribute('src')) : null;
        $this->drawRect(self::LEFT + 8, $this->cursorY - 58, 58, 50, self::BLUE, [0.83, 0.65, 0.17]);
        if ($logoName !== null) {
            $this->drawImage($logoName, self::LEFT + 12, $this->cursorY - 55, 50, 44);
        } else {
            $this->drawText('MCC', self::LEFT + 37, $this->cursorY - 34, 16, true, true, [1, 1, 1]);
        }
        $this->drawText('Mumbai Cricket Club', self::LEFT + 80, $this->cursorY - 23, 16, true, false, self::BLUE);
        $this->drawText('Admission Form', self::LEFT + 80, $this->cursorY - 40, 10, false, false, [0.15, 0.2, 0.26]);

        $meta = null;
        foreach ($header->getElementsByTagName('td') as $cell) {
            if ($cell instanceof DOMElement && str_contains((string) $cell->getAttribute('class'), 'pdf-header-meta')) {
                $meta = $cell;
                break;
            }
        }
        if ($meta instanceof DOMElement) {
            foreach ($this->directElementLines($meta) as $index => $line) {
                [$label, $value] = array_pad(explode(':', $line, 2), 2, '');
                $y = $this->cursorY - 15 - ($index * 17);
                $this->drawText(trim($label) . ':', self::PAGE_WIDTH - self::RIGHT - 170, $y, 7, true, false, self::BLUE);
                $this->drawTextBlock(trim($value), self::PAGE_WIDTH - self::RIGHT - 95, $y, 87, 7, false, [0.15, 0.2, 0.26]);
            }
        }

        $this->cursorY -= 82;
    }

    private function renderSection(DOMElement $section): void
    {
        $heading = $section->getElementsByTagName('h2')->item(0);
        if ($heading instanceof DOMElement) {
            $this->renderHeading($this->text($heading));
        }

        foreach ($section->childNodes as $child) {
            if (! $child instanceof DOMElement || $child === $heading) {
                continue;
            }

            $class = (string) $child->getAttribute('class');
            $tag = strtolower($child->tagName);
            if ($tag === 'table' && str_contains($class, 'pdf-details')) {
                $this->renderDetailsTable($child);
            } elseif ($tag === 'table') {
                if (str_contains($class, 'pdf-signatures')) {
                    $this->renderSignatures($child);
                } else {
                    $this->renderTable($child);
                }
            } elseif ($tag === 'div' && str_contains($class, 'pdf-declaration')) {
                $this->renderDeclaration($child);
            } elseif ($tag === 'div' && str_contains($class, 'pdf-signatures')) {
                $this->renderSignatures($child);
            } elseif ($tag === 'div' && str_contains($class, 'mcc-admission-terms')) {
                $this->renderTerms($child);
            }
        }

        $this->cursorY -= 8;
    }

    private function renderHeading(string $heading): void
    {
        $this->ensureSpace(27);
        $this->drawRect(self::LEFT, $this->cursorY - 20, self::PAGE_WIDTH - self::LEFT - self::RIGHT, 20, self::PALE_BLUE, null);
        $this->drawRect(self::LEFT, $this->cursorY - 20, 4, 20, self::BLUE, null);
        $this->drawText($heading, self::LEFT + 10, $this->cursorY - 14, 11, true, false, self::BLUE);
        $this->cursorY -= 29;
    }

    private function renderDetailGrid(DOMElement $grid): void
    {
        $items = [];
        foreach ($grid->childNodes as $child) {
            if (! $child instanceof DOMElement || $child->tagName !== 'div') {
                continue;
            }
            $label = $child->getElementsByTagName('dt')->item(0);
            $value = $child->getElementsByTagName('dd')->item(0);
            if ($label instanceof DOMElement && $value instanceof DOMElement) {
                $items[] = [$this->text($label), $this->text($value)];
            }
        }

        $columnWidth = (self::PAGE_WIDTH - self::LEFT - self::RIGHT - 18) / 2;
        for ($index = 0; $index < count($items); $index += 2) {
            $rowItems = [$items[$index] ?? ['', ''], $items[$index + 1] ?? ['', '']];
            $rowHeight = 30;
            foreach ($rowItems as $item) {
                $rowHeight = max($rowHeight, 21 + (count($this->wrap($item[1], $columnWidth - 10, 8)) - 1) * 10);
            }
            $this->ensureSpace($rowHeight);
            $x = self::LEFT;
            for ($column = 0; $column < 2; $column++) {
                $item = $rowItems[$column];
                $this->drawRect($x, $this->cursorY - $rowHeight, $columnWidth, $rowHeight, [1, 1, 1], self::BORDER);
                $this->drawText($item[0], $x + 5, $this->cursorY - 10, 7, true, false, [0.29, 0.34, 0.4]);
                $this->drawTextBlock($item[1], $x + 5, $this->cursorY - 21, $columnWidth - 10, 8, false, [0.07, 0.09, 0.12]);
                $x += $columnWidth + 18;
            }
            $this->cursorY -= $rowHeight;
        }
        $this->cursorY -= 5;
    }

    private function renderDetailsTable(DOMElement $table): void
    {
        $rows = [];

        foreach ($table->getElementsByTagName('tr') as $row) {
            $cells = [];

            foreach ($row->childNodes as $cell) {
                if (! $cell instanceof DOMElement || strtolower($cell->tagName) !== 'td') {
                    continue;
                }

                $label = $cell->getElementsByTagName('span')->item(0);
                $value = $cell->getElementsByTagName('span')->item(1);
                $cells[] = [
                    $label instanceof DOMElement ? $this->text($label) : '',
                    $value instanceof DOMElement ? $this->text($value) : '',
                ];
            }

            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        $columnWidth = (self::PAGE_WIDTH - self::LEFT - self::RIGHT - 10) / 2;

        foreach ($rows as $row) {
            $height = 28;

            foreach ($row as $item) {
                $height = max($height, 19 + (count($this->wrap($item[1], $columnWidth - 12, 8)) - 1) * 10);
            }

            $this->ensureSpace($height);

            foreach ($row as $index => $item) {
                $x = self::LEFT + ($index * ($columnWidth + 10));
                $this->drawRect($x, $this->cursorY - $height, $columnWidth, $height, [1, 1, 1], self::BORDER);
                $this->drawText($item[0], $x + 6, $this->cursorY - 10, 7, true, false, [0.29, 0.34, 0.4]);
                $this->drawTextBlock($item[1], $x + 6, $this->cursorY - 21, $columnWidth - 12, 8, false, [0.07, 0.09, 0.12]);
            }

            if (count($row) === 1) {
                $x = self::LEFT + $columnWidth + 10;
                $this->drawRect($x, $this->cursorY - $height, $columnWidth, $height, [1, 1, 1], self::BORDER);
            }

            $this->cursorY -= $height;
        }

        $this->cursorY -= 5;
    }

    private function renderTable(DOMElement $table): void
    {
        $rows = [];
        foreach ($table->getElementsByTagName('tr') as $row) {
            $cells = [];
            foreach ($row->childNodes as $cell) {
                if ($cell instanceof DOMElement && in_array(strtolower($cell->tagName), ['th', 'td'], true)) {
                    $cells[] = $this->text($cell);
                }
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }
        if ($rows === []) {
            return;
        }

        $columns = max(array_map('count', $rows));
        $width = (self::PAGE_WIDTH - self::LEFT - self::RIGHT) / $columns;
        foreach ($rows as $rowIndex => $row) {
            $wrapped = [];
            $height = 22;
            for ($column = 0; $column < $columns; $column++) {
                $lines = $this->wrap($row[$column] ?? '', $width - 12, 8);
                $wrapped[$column] = $lines;
                $height = max($height, count($lines) * 10 + 12);
            }
            $this->ensureSpace($height);
            for ($column = 0; $column < $columns; $column++) {
                $x = self::LEFT + ($column * $width);
                $fill = $rowIndex === 0 ? self::BLUE : ($rowIndex % 2 === 0 ? [1, 1, 1] : [0.969, 0.976, 0.984]);
                $this->drawRect($x, $this->cursorY - $height, $width, $height, $fill, self::BORDER);
                foreach ($wrapped[$column] as $lineIndex => $line) {
                    $this->drawText($line, $x + 6, $this->cursorY - 12 - ($lineIndex * 10), 8, $rowIndex === 0, false, $rowIndex === 0 ? [1, 1, 1] : [0.07, 0.09, 0.12]);
                }
            }
            $this->cursorY -= $height;
        }
        $this->cursorY -= 8;
    }

    private function renderDeclaration(DOMElement $declaration): void
    {
        $lines = $this->wrap($this->text($declaration), self::PAGE_WIDTH - self::LEFT - self::RIGHT - 18, 9);
        $height = max(32, count($lines) * 12 + 16);
        $this->ensureSpace($height);
        $this->drawRect(self::LEFT, $this->cursorY - $height, self::PAGE_WIDTH - self::LEFT - self::RIGHT, $height, [1, 1, 1], self::BORDER);
        foreach ($lines as $index => $line) {
            $this->drawText($line, self::LEFT + 9, $this->cursorY - 13 - ($index * 12), 9);
        }
        $this->cursorY -= $height + 6;
    }

    private function renderSignatures(DOMElement $signatures): void
    {
        $items = [];
        foreach ($signatures->getElementsByTagName('td') as $child) {
            if ($child instanceof DOMElement) {
                $label = $child->getElementsByTagName('span')->item(0);
                $box = null;
                foreach ($child->getElementsByTagName('div') as $candidate) {
                    if ($candidate instanceof DOMElement && str_contains((string) $candidate->getAttribute('class'), 'pdf-signature-box')) {
                        $box = $candidate;
                        break;
                    }
                }
                $image = $box instanceof DOMElement ? $box->getElementsByTagName('img')->item(0) : null;
                $items[] = [
                    'label' => $label instanceof DOMElement ? $this->text($label) : 'Signature',
                    'box' => $box,
                    'image' => $image instanceof DOMElement ? $this->registerImage($image->getAttribute('src')) : null,
                    'text' => $box instanceof DOMElement ? $this->text($box) : '',
                ];
            }
        }
        $this->ensureSpace(78);
        $width = (self::PAGE_WIDTH - self::LEFT - self::RIGHT - 24) / 3;
        foreach ($items as $index => $item) {
            $x = self::LEFT + ($index * ($width + 12));
            $this->drawTextBlock($item['label'], $x, $this->cursorY - 10, $width, 8, true, self::BLUE);
            $this->drawRect($x, $this->cursorY - 70, $width, 44, [1, 1, 1], self::BORDER);
            if ($item['image'] !== null) {
                $this->drawImage($item['image'], $x + 5, $this->cursorY - 65, $width - 10, 34);
            } else {
                $this->drawTextBlock($item['text'], $x + 5, $this->cursorY - 47, $width - 10, 7, false, [0.29, 0.34, 0.4]);
            }
        }
        $this->cursorY -= 78;
    }

    private function renderTerms(DOMElement $terms): void
    {
        foreach ($terms->childNodes as $child) {
            if ($child instanceof DOMElement && $child->tagName === 'section') {
                $heading = $child->getElementsByTagName('h3')->item(0);
                if ($heading instanceof DOMElement) {
                    $this->ensureSpace(20);
                    $this->drawText($this->text($heading), self::LEFT, $this->cursorY - 10, 10, true, false, self::BLUE);
                    $this->cursorY -= 17;
                }
                foreach ($child->childNodes as $content) {
                    if (! $content instanceof DOMElement || $content === $heading) {
                        continue;
                    }
                    if ($content->tagName === 'p') {
                        $this->renderParagraph($this->text($content), 8);
                    } elseif ($content->tagName === 'ul') {
                        $this->renderList($content);
                    }
                }
                $this->cursorY -= 4;
            }
        }
    }

    private function renderParagraph(string $text, int $size): void
    {
        foreach ($this->wrap($text, self::PAGE_WIDTH - self::LEFT - self::RIGHT, $size) as $line) {
            $this->ensureSpace($size + 5);
            $this->drawText($line, self::LEFT, $this->cursorY - $size, $size);
            $this->cursorY -= $size + 4;
        }
    }

    private function renderList(DOMElement $list): void
    {
        foreach ($list->childNodes as $child) {
            if (! $child instanceof DOMElement || $child->tagName !== 'li') {
                continue;
            }
            $itemText = preg_replace('/^o\s+/i', '', $this->text($child)) ?? $this->text($child);
            $lines = $this->wrap($itemText, self::PAGE_WIDTH - self::LEFT - self::RIGHT - 14, 8);
            $this->ensureSpace((count($lines) * 10) + 2);
            foreach ($lines as $index => $line) {
                $this->drawText(($index === 0 ? '- ' : '  ') . $line, self::LEFT + 6, $this->cursorY - 8, 8);
                $this->cursorY -= 10;
            }
            $this->cursorY -= 2;
        }
    }

    /** @return list<string> */
    private function elementLines(DOMElement $element): array
    {
        $lines = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $lines[] = $this->text($child);
            }
        }

        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }

    /** @return list<string> */
    private function directElementLines(DOMElement $element): array
    {
        $lines = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $lines[] = $this->text($child);
            }
        }

        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }

    private function text(DOMNode $node): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /** @return list<string> */
    private function wrap(string $text, float $width, int $fontSize): array
    {
        $characters = max(12, (int) floor($width / ($fontSize * 0.5)));
        $wrapped = wordwrap($this->ascii($text), $characters, "\n", true);

        return explode("\n", $wrapped === '' ? ' ' : $wrapped);
    }

    private function drawTextBlock(string $text, float $x, float $y, float $width, int $size, bool $bold, array $color): void
    {
        foreach ($this->wrap($text, $width, $size) as $index => $line) {
            $this->drawText($line, $x, $y - ($index * ($size + 2)), $size, $bold, false, $color);
        }
    }

    private function registerImage(string $source): ?string
    {
        $bytes = null;

        if (str_starts_with($source, 'data:image/')) {
            $parts = explode(',', $source, 2);
            if (count($parts) === 2 && str_contains($parts[0], ';base64')) {
                $bytes = base64_decode($parts[1], true);
            }
        } elseif (! str_contains($source, '://')) {
            $path = is_file($source)
                ? $source
                : (defined('ROOTPATH') ? ROOTPATH . ltrim($source, '/\\') : $source);

            if (is_file($path)) {
                $bytes = file_get_contents($path);
            }
        }

        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
        ob_start();
        imagejpeg($canvas, null, 88);
        $jpeg = ob_get_clean();
        imagedestroy($image);
        imagedestroy($canvas);

        if (! is_string($jpeg) || $jpeg === '') {
            return null;
        }

        $name = 'Im' . (count($this->images) + 1);
        $this->images[$name] = [
            'jpeg' => $jpeg,
            'width' => $width,
            'height' => $height,
        ];

        return $name;
    }

    private function drawImage(string $name, float $x, float $y, float $maxWidth, float $maxHeight): void
    {
        $image = $this->images[$name] ?? null;
        if ($image === null) {
            return;
        }

        $scale = min($maxWidth / $image['width'], $maxHeight / $image['height']);
        $width = $image['width'] * $scale;
        $height = $image['height'] * $scale;
        $offsetX = ($maxWidth - $width) / 2;
        $offsetY = ($maxHeight - $height) / 2;

        $this->command(
            'q ' . $this->formatNumber($width) . ' 0 0 ' . $this->formatNumber($height) . ' '
            . $this->formatNumber($x + $offsetX) . ' ' . $this->formatNumber($y + $offsetY)
            . ' cm /' . $name . ' Do Q'
        );
    }

    private function startPage(): void
    {
        $this->pages[] = [];
        $this->cursorY = self::PAGE_HEIGHT - self::TOP;
    }

    private function finishPage(): void
    {
        $pageNumber = count($this->pages);
        $this->drawLine(self::LEFT, 30, self::PAGE_WIDTH - self::RIGHT, 30, self::BORDER);
        $this->drawText('Mumbai Cricket Club | Admission document', self::LEFT, 18, 7, false, false, [0.29, 0.34, 0.4]);
        $footer = 'Page ' . $pageNumber . ' | ' . date('d-m-Y');
        $footerWidth = strlen($footer) * 7 * 0.48;
        $this->drawText($footer, self::PAGE_WIDTH - self::RIGHT - $footerWidth, 18, 7, false, false, [0.29, 0.34, 0.4]);
    }

    private function ensureSpace(float $height): void
    {
        if ($this->cursorY - $height < self::BOTTOM) {
            $this->finishPage();
            $this->startPage();
        }
    }

    private function drawText(string $text, float $x, float $y, int $size, bool $bold = false, bool $center = false, array $color = [0.07, 0.09, 0.12]): void
    {
        $font = $bold ? 'F2' : 'F1';
        $textWidth = strlen($text) * $size * 0.48;
        if ($center) {
            $x -= $textWidth / 2;
        }
        $escaped = $this->escapePdfText($this->ascii($text));
        $this->command(sprintf('BT /%s %d Tf %s rg %s %s Td (%s) Tj ET', $font, $size, $this->color($color), $this->formatNumber($x), $this->formatNumber($y), $escaped));
    }

    private function drawRect(float $x, float $y, float $width, float $height, array $fill, ?array $stroke): void
    {
        $command = 'q ' . $this->color($fill) . ' rg ' . $this->formatNumber($x) . ' ' . $this->formatNumber($y) . ' ' . $this->formatNumber($width) . ' ' . $this->formatNumber($height) . ' re f Q';
        if ($stroke !== null) {
            $command .= ' q ' . $this->color($stroke) . ' RG 0.5 w ' . $this->formatNumber($x) . ' ' . $this->formatNumber($y) . ' ' . $this->formatNumber($width) . ' ' . $this->formatNumber($height) . ' re S Q';
        }
        $this->command($command);
    }

    private function drawLine(float $x1, float $y1, float $x2, float $y2, array $color): void
    {
        $this->command('q ' . $this->color($color) . ' RG 0.6 w ' . $this->formatNumber($x1) . ' ' . $this->formatNumber($y1) . ' m ' . $this->formatNumber($x2) . ' ' . $this->formatNumber($y2) . ' l S Q');
    }

    private function command(string $command): void
    {
        $this->pages[array_key_last($this->pages)][] = $command;
    }

    private function color(array $color): string
    {
        return implode(' ', array_map(fn (float $value): string => $this->formatNumber($value), $color));
    }

    private function formatNumber(float $number): string
    {
        return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
    }

    private function buildPdf(): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];
        $pageObjects = [];
        $contentObjects = [];
        $next = 5;
        foreach ($this->pages as $page) {
            $pageObjects[] = $next++;
            $contentObjects[] = $next++;
        }
        $imageObjects = [];
        foreach ($this->images as $name => $image) {
            $imageObjects[$name] = $next++;
        }
        $kids = implode(' ', array_map(static fn (int $number): string => $number . ' 0 R', $pageObjects));
        $objects[1] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($pageObjects) . ' >>';

        foreach ($this->pages as $index => $page) {
            $content = implode("\n", $page) . "\n";
            $pageObject = $pageObjects[$index];
            $contentObject = $contentObjects[$index];
            $xObjects = [];
            foreach ($imageObjects as $name => $imageObject) {
                $xObjects[] = '/' . $name . ' ' . $imageObject . ' 0 R';
            }
            $xObjectResource = $xObjects === [] ? '' : ' /XObject << ' . implode(' ', $xObjects) . ' >>';
            $objects[$pageObject - 1] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>' . $xObjectResource . ' >> /Contents ' . $contentObject . ' 0 R >>';
            $objects[$contentObject - 1] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream';
        }

        foreach ($imageObjects as $name => $imageObject) {
            $image = $this->images[$name];
            $objects[$imageObject - 1] = '<< /Type /XObject /Subtype /Image /Width ' . $image['width']
                . ' /Height ' . $image['height'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8'
                . ' /Filter /DCTDecode /Length ' . strlen($image['jpeg']) . " >>\nstream\n"
                . $image['jpeg'] . "\nendstream";
        }

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $number = $index + 1;
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($number = 1; $number <= count($objects); $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

        return $pdf;
    }

    private function ascii(string $value): string
    {
        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);

        return $converted === false ? preg_replace('/[^\x20-\x7E]/', '?', $value) : $converted;
    }

    private function escapePdfText(string $value): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ''], $value);
    }
}
