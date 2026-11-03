<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class EncodingHelperTest extends CIUnitTestCase
{
    public function testMojibakeEmDashIsNormalizedToUtf8(): void
    {
        require_once APPPATH . 'Helpers/encoding_helper.php';
        $corrupt = mb_convert_encoding(mb_convert_encoding('—', 'Windows-1252', 'UTF-8'), 'UTF-8', 'Windows-1252');

        self::assertSame('—', mcc_normalize_mojibake($corrupt));
        self::assertSame('—', mcc_normalize_mojibake('—'));
    }

    public function testMeaningfulUtf8TextIsUnchanged(): void
    {
        require_once APPPATH . 'Helpers/encoding_helper.php';

        self::assertSame('MCC — Cricket Club', mcc_normalize_mojibake('MCC — Cricket Club'));
        self::assertSame('José', mcc_normalize_mojibake('José'));
        self::assertSame('Ãngström', mcc_normalize_mojibake('Ãngström'));
    }

    public function testApplicationViewSourcesNormalizeWithoutMojibakeMarkers(): void
    {
        require_once APPPATH . 'Helpers/encoding_helper.php';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(APPPATH));
        $checked = 0;

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php' || $file->getFilename() === 'encoding_helper.php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            self::assertStringNotContainsString('Ã', mcc_normalize_mojibake($source), $file->getPathname());
            $checked++;
        }

        self::assertGreaterThan(0, $checked);
    }
}
