<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services;

use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\PersistentCollection;
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
use function get_class;
use function implode;
use function is_array;
use function is_bool;
use function method_exists;
use function sprintf;

class Exporter implements ExporterInterface
{
    public function __construct(
        private readonly Spreadsheet $spreadsheet,
    ) {
    }

    #[Override]
    public function exportXlsx(Query $query, array $methods, string $fileName): StreamedResponse
    {
        $results = $query->getResult();

        if (null === $results || [] === $results) {
            throw new InvalidArgumentException('There are no results to export');
        }

        if (null === $methods || [] === $methods) {
            throw new InvalidArgumentException('Methods cannot be empty');
        }

        $sheet = $this->spreadsheet->getActiveSheet();

        foreach ($methods as $col => $field) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $field);
        }

        $values = $this->formatValues($results, $methods);

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
        $results = $query->getResult();

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

    private function formatValues(array $values, array $methods): array
    {
        return array_map(function ($entity) use ($methods) {
            return array_map(function ($method) use ($entity) {
                if (method_exists($entity, $method)) {
                    return $this->formatValue($entity->$method());
                }

                throw new InvalidArgumentException(sprintf('Method %s does not exist on entity %s', $method, get_class($entity)));
            }, $methods);
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

        if ($value instanceof ArrayCollection || $value instanceof PersistentCollection) {
            return implode(', ', $value->toArray());
        }

        return (string) $value;
    }
}
