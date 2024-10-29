<?php

namespace SymfonyImportExportBundle\Services;

use Doctrine\ORM\QueryBuilder;
use Override;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Exporter implements ExporterInterface
{
    public function __construct(
        private readonly Spreadsheet $spreadsheet
    ) {
    }

    #[Override]
    public function export(QueryBuilder $queryBuilder, array $fields): StreamedResponse
    {
        $results = $queryBuilder->getQuery()->getArrayResult();

        if ([] === $results || [] === $fields) {
            throw new \InvalidArgumentException('Fields cannot be empty');
        }

        $sheet = $this->spreadsheet->getActiveSheet();

        foreach ($fields as $col => $field) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, ucfirst($field));
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
        $response->headers->set('Content-Disposition', 'attachment;filename="export.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
