<?php

declare(strict_types=1);

namespace App\Modules\Documents\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Browser extends StaffController
{
    public function index(): string
    {
        return $this->page(
            'App\\Modules\\Documents\\Views\\index',
            'Documents',
            ['result' => service('document')->list($this->request->getGet())]
        );
    }

    public function show(int $id): string|ResponseInterface
    {
        try {
            $document = service('document')->find($id);
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode(404)
                ->setBody('Not found.');
        }

        return $this->page(
            'App\\Modules\\Documents\\Views\\show',
            'Document detail',
            [
                'document' => $document,
                'can_verify' => service('authorization')->can(
                    'document.upload',
                    (string) $document['branch_id']
                ),
            ]
        );
    }

    public function download(int $id): ResponseInterface
    {
        try {
            $file = service('document')->read($id);
            $document = $file['document'];
            $name = $this->safeDownloadName($document);

            return $this->response
                ->setHeader('Content-Type', (string) $document['mime_type'])
                ->setHeader('Content-Length', (string) strlen($file['contents']))
                ->setHeader('X-Content-Type-Options', 'nosniff')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
                ->setBody($file['contents']);
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode(404)
                ->setBody('Not found.');
        }
    }

    public function preview(int $id): ResponseInterface
    {
        try {
            $file = service('document')->read($id);
            $document = $file['document'];

            if (! in_array(
                (string) $document['mime_type'],
                ['application/pdf', 'image/jpeg', 'image/png'],
                true
            )) {
                throw new \RuntimeException('Preview is unavailable.');
            }

            $mimeType = (string) $document['mime_type'];
            $disposition = 'inline';

            if ($mimeType === 'application/pdf') {
                $name = $this->safeDownloadName($document);
                $disposition = 'inline; filename="' . $name . '"';
            }

            return $this->response
                ->setContentType($mimeType)
                ->setHeader('Content-Length', (string) strlen($file['contents']))
                ->setHeader('X-Content-Type-Options', 'nosniff')
                ->setHeader('Content-Disposition', $disposition)
                ->setHeader('Cache-Control', 'private, no-store')
                ->setBody($file['contents']);
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode(404)
                ->setBody('Not found.');
        }
    }

    /** @param array<string, mixed> $document */
    private static function safeDownloadName(array $document): string
    {
        $type = strtolower((string) ($document['document_type'] ?? 'document'));
        $type = preg_replace('/[^a-z0-9]+/', '_', $type) ?? 'document';
        $type = trim($type, '_') ?: 'document';

        $extension = match ((string) ($document['mime_type'] ?? '')) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'bin',
        };

        return $type . '.' . $extension;
    }

    public function upload(): ResponseInterface
    {
        try {
            $id = service('document')->upload(
                $this->request->getPost(),
                $this->request->getFile('document')
            );

            return redirect()->to('/documents/' . $id);
        } catch (Throwable $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $this->safe($exception));
        }
    }

    public function verify(int $id): ResponseInterface
    {
        try {
            $data = $this->request->getPost();
            service('document')->verify(
                $id,
                (string) ($data['verification_status'] ?? ''),
                $data['verification_reason'] ?? null,
            );

            return redirect()->to('/documents/' . $id)
                ->with('message', 'Document verification updated.');
        } catch (Throwable $exception) {
            return redirect()
                ->to('/documents/' . $id)
                ->withInput()
                ->with('error', $this->safe($exception));
        }
    }
}
