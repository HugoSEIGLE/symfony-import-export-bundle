<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services;

use Doctrine\ORM\Query;
use InvalidArgumentException;
use Override;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function fclose;
use function fopen;
use function fputcsv;

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

        foreach ($results as $rowIndex => $result) {
            foreach ($fields as $colIndex => $field) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 2, $result[$field] ?? '');
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

            foreach ($results as $result) {
                fputcsv($handle, $result);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment;filename="export.csv"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
