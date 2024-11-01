<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services\Import;

use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyImportExportBundle\Services\MethodToSnakeInterface;

use function array_key_exists;
use function array_map;
use function class_exists;
use function fclose;
use function fopen;
use function fputcsv;
use function in_array;
use function is_array;

class ImporterTemplate implements ImporterTemplateInterface
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly TranslatorInterface $translator,
        private readonly MethodToSnakeInterface $methodToSnake,
    ) {
    }

    public function getImportTemplate(string $class, string $fileType): StreamedResponse
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException('Class must be an object.');
        }

        if (!in_array($fileType, [ImporterInterface::XLSX, ImporterInterface::CSV])) {
            throw new InvalidArgumentException('Invalid file type.');
        }

        $importersConfig = $this->parameterBag->get('import_export.importers');

        if (!is_array($importersConfig)) {
            throw new InvalidArgumentException('Importers configuration not found.');
        }

        if (!array_key_exists($class, $importersConfig)) {
            throw new InvalidArgumentException('Class not found in importers configuration.');
        }

        $fields = $importersConfig[$class]['fields'];

        if ($importersConfig[$class]['allow_delete']) {
            $fields[] = 'deleted';
        }

        return match ($fileType) {
            ImporterInterface::XLSX => $this->getXlsxTemplate($fields),
            ImporterInterface::CSV => $this->getCsvTemplate($fields),
        };
    }

    /**
     * @param array<string> $fields
     */
    private function getXlsxTemplate(array $fields): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($fields as $col => $field) {
            $translatedField = $this->getTranslatedField($field);
            $sheet->setCellValueByColumnAndRow($col + 1, 1, '' === $translatedField ? $field : $translatedField);
        }

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new XlsxWriter($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="import_template.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    /**
     * @param array<string> $fields
     */
    private function getCsvTemplate(array $fields): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($fields) {
            $handle = fopen('php://output', 'w');

            if (false === $handle) {
                return;
            }

            $translatedFields = array_map(
                fn ($field) => $this->getTranslatedField($field),
                $fields
            );

            fputcsv($handle, $translatedFields);

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment;filename="import_template.csv"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    private function getTranslatedField(string $field): string
    {
        $translatedField = $this->translator->trans('import_export.' . $this->methodToSnake->convert($field), [], 'messages');

        return '' === $translatedField ? $field : $translatedField;
    }
}
