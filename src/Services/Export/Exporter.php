<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services\Export;

use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Query;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyImportExportBundle\Services\MethodToSnakeInterface;

use function array_map;
use function fclose;
use function fopen;
use function fputcsv;
use function get_class;
use function gettype;
use function implode;
use function is_array;
use function is_bool;
use function is_object;
use function is_scalar;
use function method_exists;
use function sprintf;

class Exporter implements ExporterInterface
{
    public function __construct(
        private readonly Spreadsheet $spreadsheet,
        private readonly TranslatorInterface $translator,
        private readonly MethodToSnakeInterface $methodToSnake,
        private readonly string $dateFormat = 'Y-m-d H:i:s',
        private readonly string $boolTrue = 'true',
        private readonly string $boolFalse = 'false',
    ) {
    }

    public function export(Query $query, array $methods, string $fileName, string $fileType): StreamedResponse
    {
        $results = $this->getResults($query);
        $translatedHeaders = $this->getTranslatedHeaders($methods);

        return match ($fileType) {
            self::XLSX => $this->exportXlsx($results, $translatedHeaders, $methods, $fileName),
            self::CSV => $this->exportCsv($results, $translatedHeaders, $methods, $fileName),
            default => throw new InvalidArgumentException(sprintf('Unsupported file type %s', $fileType)),
        };
    }

    /**
     * @param array<string> $methods
     * @param array<object> $results
     * @param array<string> $translatedHeaders
     */
    private function exportXlsx(array $results, array $translatedHeaders, array $methods, string $fileName): StreamedResponse
    {
        $sheet = $this->spreadsheet->getActiveSheet();
        $this->writeHeadersToSheet($sheet, $translatedHeaders);
        $this->writeValuesToSheet($sheet, $this->formatValues($results, $methods));

        return $this->createStreamedResponse($fileName);
    }

    /**
     * @param array<string> $methods
     * @param array<object> $results
     * @param array<string> $translatedHeaders
     */
    private function exportCsv(array $results, array $translatedHeaders, array $methods, string $fileName): StreamedResponse
    {
        return new StreamedResponse(function () use ($results, $translatedHeaders, $methods) {
            $handle = fopen('php://output', 'w');
            if (false === $handle) {
                throw new RuntimeException('Could not open output stream.');
            }

            fputcsv($handle, $translatedHeaders);

            foreach ($this->formatValues($results, $methods) as $value) {
                fputcsv($handle, $value);
            }

            fclose($handle);
        }, 200, $this->getCsvHeaders($fileName));
    }

    /**
     * @return array<int, object>
     */
    private function getResults(Query $query): array
    {
        $results = $query->getResult();
        if (!is_array($results)) {
            throw new InvalidArgumentException('Expected query result to be an array.');
        }

        if ([] === $results) {
            throw new InvalidArgumentException('There are no results to export');
        }

        return $results;
    }

    /**
     * @param array<string> $methods
     *
     * @return array<string>
     */
    private function getTranslatedHeaders(array $methods): array
    {
        return array_map(fn (string $method) => $this->translator->trans('import_export.' . $this->methodToSnake->convert($method), [], 'messages'), $methods);
    }

    /**
     * @param array<string> $headers
     */
    private function writeHeadersToSheet(Worksheet $sheet, array $headers): void
    {
        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }
    }

    /**
     * @param array<array<string>> $values
     */
    private function writeValuesToSheet(Worksheet $sheet, array $values): void
    {
        foreach ($values as $row => $value) {
            foreach ($value as $col => $val) {
                $sheet->setCellValueByColumnAndRow($col + 1, $row + 2, $val);
            }
        }
    }

    private function createStreamedResponse(string $fileName): StreamedResponse
    {
        $response = new StreamedResponse(function () {
            $writer = new Xlsx($this->spreadsheet);
            $writer->save('php://output');
        });

        $headers = $this->getXlsxHeaders($fileName);
        $response->headers->add($headers);

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function getXlsxHeaders(string $fileName): array
    {
        return [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => sprintf('attachment;filename="%s.xlsx"', $fileName),
            'Cache-Control' => 'max-age=0',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getCsvHeaders(string $fileName): array
    {
        return [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => sprintf('attachment;filename="%s.csv"', $fileName),
            'Cache-Control' => 'max-age=0',
        ];
    }

    /**
     * @param array<object> $values
     * @param array<string> $methods
     *
     * @return array<array<string>>
     */
    private function formatValues(array $values, array $methods): array
    {
        return array_map(function ($entity) use ($methods) {
            return array_map(function (string $method) use ($entity) {
                if (!method_exists($entity, $method)) {
                    throw new InvalidArgumentException(sprintf('Method %s does not exist on entity %s', $method, get_class($entity)));
                }

                return $this->formatValue($entity->$method());
            }, $methods);
        }, $values);
    }

    private function formatValue(mixed $value): string
    {
        return match (true) {
            null === $value => '',
            is_bool($value) => $value ? $this->boolTrue : $this->boolFalse,
            is_array($value) => implode(', ', $value),
            $value instanceof DateTimeInterface => $value->format($this->dateFormat),
            $value instanceof ArrayCollection || $value instanceof PersistentCollection => implode(', ', $value->toArray()),
            is_scalar($value) => (string) $value,
            is_object($value) => method_exists($value, '__toString') ? (string) $value : throw new InvalidArgumentException(sprintf('Cannot cast object of class %s to string', get_class($value))),
            default => throw new InvalidArgumentException(sprintf('Cannot cast value of type %s to string', gettype($value))),
        };
    }
}
