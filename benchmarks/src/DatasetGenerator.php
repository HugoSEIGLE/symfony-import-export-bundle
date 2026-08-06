<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Benchmarks;

use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

use function fclose;
use function fopen;
use function fputcsv;
use function sprintf;

final class DatasetGenerator
{
    /** @return array{name: string, email: string, employees: int, active: bool} */
    public function row(int $index): array
    {
        if (1 > $index) {
            throw new InvalidArgumentException('Dataset row indexes start at 1.');
        }

        return [
            'name' => sprintf('Company %06d', $index),
            'email' => sprintf('company-%06d@example.test', $index),
            'employees' => $index % 1000,
            'active' => 0 === $index % 2,
        ];
    }

    public function createImportFile(string $path, string $format, int $rows): void
    {
        match ($format) {
            'csv' => $this->createCsv($path, $rows),
            'xlsx' => $this->createXlsx($path, $rows),
            default => throw new InvalidArgumentException(sprintf('Unsupported dataset format %s.', $format)),
        };
    }

    private function createCsv(string $path, int $rows): void
    {
        $handle = fopen($path, 'wb');
        if (false === $handle) {
            throw new RuntimeException(sprintf('Unable to create %s.', $path));
        }

        try {
            fputcsv($handle, ['name', 'email', 'employees', 'active'], ',', '"', '');
            for ($index = 1; $index <= $rows; ++$index) {
                $row = $this->row($index);
                fputcsv($handle, [
                    $row['name'],
                    $row['email'],
                    (string) $row['employees'],
                    $row['active'] ? 'true' : 'false',
                ], ',', '"', '');
            }
        } finally {
            fclose($handle);
        }
    }

    private function createXlsx(string $path, int $rows): void
    {
        $spreadsheet = new Spreadsheet();

        try {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray(['name', 'email', 'employees', 'active'], null, 'A1');
            for ($index = 1; $index <= $rows; ++$index) {
                $row = $this->row($index);
                $sheet->fromArray([
                    $row['name'],
                    $row['email'],
                    (string) $row['employees'],
                    $row['active'] ? 'true' : 'false',
                ], null, 'A' . ($index + 1));
            }
            (new Xlsx($spreadsheet))->save($path);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }
}
