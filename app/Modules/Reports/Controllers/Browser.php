<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;
use RuntimeException;
use Throwable;

final class Browser extends StaffController
{
    public function index(): string
    {
        $db = Database::connect('default');
        $branchIds = service('branchContext')->authorizedBranchIds(true);
        $branches = $branchIds === []
            ? []
            : $db->table('branches')
                ->select('id, name')
                ->whereIn('id', $branchIds)
                ->orderBy('name', 'ASC')
                ->get()
                ->getResultArray();

        return $this->page(
            'App\\Modules\\Reports\\Views\\index',
            'Reports',
            [
                'catalog' => service('report')->catalog(),
                'branches' => $branches,
            ]
        );
    }

    public function show(string $type): ResponseInterface|string
    {
        try {
            $result = service('report')->run($type, $this->request->getGet());
        } catch (Throwable $exception) {
            if ($exception->getMessage() === 'Forbidden') {
                return $this->response
                    ->setStatusCode(403)
                    ->setBody(esc($this->safe($exception)));
            }

            if ($exception->getMessage() === 'Invalid report.') {
                return $this->response
                    ->setStatusCode(404)
                    ->setBody('Report not found.');
            }

            if ($exception->getMessage() !== 'Invalid report date filter.') {
                throw $exception;
            }

            $result = [
                'type' => $type,
                'title' => ucwords(str_replace('_', ' ', $type)),
                'rows' => [],
                'filters' => $this->request->getGet(),
            ];

            return $this->page(
                'App\\Modules\\Reports\\Views\\show',
                $result['title'] . ' report',
                [
                    'result' => $result,
                    'error' => $this->safe($exception),
                    'branches' => $this->reportBranches(),
                ]
            );
        }

        return $this->page(
            'App\\Modules\\Reports\\Views\\show',
            $result['title'] . ' report',
            [
                'result' => $result,
                'branches' => $this->reportBranches(),
            ]
        );
    }

    public function csv(string $type): ResponseInterface
    {
        try {
            $result = service('report')->run($type, $this->request->getGet());
            $handle = fopen('php://temp', 'r+');

            if ($handle === false) {
                throw new RuntimeException('Unable to create report export.');
            }

            $rows = $result['rows'];
            if ($rows !== []) {
                fputcsv($handle, array_keys($rows[0]));
                foreach ($rows as $row) {
                    fputcsv($handle, $row);
                }
            }

            rewind($handle);
            $csv = stream_get_contents($handle) ?: '';
            fclose($handle);

            return $this->response
                ->setHeader('Content-Type', 'text/csv; charset=utf-8')
                ->setHeader(
                    'Content-Disposition',
                    'attachment; filename="' . $type . '-report.csv"'
                )
                ->setBody($csv);
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 400)
                ->setBody(esc($this->safe($exception)));
        }
    }

    /** @return list<array{id:int,name:string}> */
    private function reportBranches(): array
    {
        $ids = service('branchContext')->authorizedBranchIds(true);
        if ($ids === []) {
            return [];
        }

        return Database::connect('default')
            ->table('branches')
            ->select('id, name')
            ->whereIn('id', $ids)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }
}
