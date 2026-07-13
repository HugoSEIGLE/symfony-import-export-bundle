<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services\Import;

use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
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
use function fwrite;
use function in_array;
use function is_array;
use function is_string;
use function strtolower;

class ImporterTemplate implements ImporterTemplateInterface
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly TranslatorInterface $translator,
        private readonly MethodToSnakeInterface $methodToSnake,
        private readonly string $csvDelimiter = ',',
        private readonly string $csvEnclosure = '"',
        private readonly string $csvEscape = '\\',
        private readonly bool $csvBom = false,
    ) {
    }

    public function getImportTemplate(string $class, string $fileType): StreamedResponse
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException('Class must be an object.');
        }

        $fileType = strtolower($fileType);
        if (!in_array($fileType, [ImporterInterface::XLSX, ImporterInterface::CSV], true)) {
            throw new InvalidArgumentException('Invalid file type.');
        }

        $importersConfig = $this->parameterBag->get('import_export.importers');

        if (!is_array($importersConfig)) {
            throw new InvalidArgumentException('Importers configuration not found.');
        }

        if (!array_key_exists($class, $importersConfig)) {
            throw new InvalidArgumentException('Class not found in importers configuration.');
        }

        $importerConfig = $importersConfig[$class];
        if (!is_array($importerConfig)) {
            throw new InvalidArgumentException('Invalid importer configuration.');
        }

        $configuredFields = $importerConfig['fields'] ?? null;
        if (!is_array($configuredFields)) {
            throw new InvalidArgumentException('Importer fields must be an array.');
        }

        $fields = [];
        foreach ($configuredFields as $field) {
            if (!is_string($field)) {
                throw new InvalidArgumentException('Importer field names must be strings.');
            }
            $fields[] = $field;
        }

        if ($importerConfig['allow_delete'] ?? false) {
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
            $cell = Coordinate::stringFromColumnIndex($col + 1) . '1';
            $sheet->setCellValue($cell, '' === $translatedField ? $field : $translatedField);
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

            if ($this->csvBom) {
                fwrite($handle, "\xEF\xBB\xBF");
            }

            $translatedFields = array_map(
                fn ($field) => $this->getTranslatedField($field),
                $fields
            );

            fputcsv($handle, $translatedFields, $this->csvDelimiter, $this->csvEnclosure, $this->csvEscape);

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
