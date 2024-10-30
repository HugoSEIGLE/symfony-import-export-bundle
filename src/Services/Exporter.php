<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services;

use DateTimeInterface;
use Doctrine\ORM\Query;
use InvalidArgumentException;
use Override;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function array_map;
use function fclose;
use function fopen;
use function fputcsv;
use function implode;
use function is_array;
use function is_bool;

class Exporter implements ExporterInterface
{
    public function __construct(
        private readonly Spreadsheet $spreadsheet,
    ) {
    }

    #[Override]
    public function exportXlsx(Query $query, array $fields, string $fileName): StreamedResponse
    {
        $results = $query->getArrayResult();

        if ([] === $results) {
            throw new InvalidArgumentException('There are no results to export');
        }

        if ([] === $fields) {
            throw new InvalidArgumentException('Fields cannot be empty');
        }

        $sheet = $this->spreadsheet->getActiveSheet();

        foreach ($fields as $col => $field) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $field);
        }

        $values = $this->formatValues($results, $fields);

        foreach ($values as $row => $value) {
            foreach ($value as $col => $val) {
                $sheet->setCellValueByColumnAndRow($col + 1, $row + 2, $val);
            }
        }

        $response = new StreamedResponse(function () {
            $writer = new Xlsx($this->spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $fileName . '.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    #[Override]
    public function exportCsv(Query $query, array $fields, string $fileName): StreamedResponse
    {
        $results = $query->getArrayResult();

        if ([] === $results) {
            throw new InvalidArgumentException('There are no results to export');
        }

        if ([] === $fields) {
            throw new InvalidArgumentException('Fields cannot be empty');
        }

        $response = new StreamedResponse(function () use ($results, $fields) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $fields);

            $values = $this->formatValues($results, $fields);

            foreach ($values as $value) {
                fputcsv($handle, $value);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment;filename="export.csv"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    private function formatValues(array $values, array $fields): array
    {
        return array_map(function ($result) use ($fields) {
            return array_map(fn ($field) => $this->formatValue($result[$field] ?? ''), $fields);
        }, $values);
    }

    private function formatValue(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }
}
