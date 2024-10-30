<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services;

use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Query;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        private readonly TranslatorInterface $translator,
        private readonly MethodToSnakeInterface $methodToSnake,
        private readonly string $dateFormat = 'Y-m-d H:i:s',
        private readonly string $boolTrue = 'true',
        private readonly string $boolFalse = 'false',
    ) {
    }

    public function exportXlsx(Query $query, array $methods, string $fileName): StreamedResponse
    {
        $results = $this->getResults($query);
        $translatedHeaders = $this->getTranslatedHeaders($methods);

        $sheet = $this->spreadsheet->getActiveSheet();
        $this->writeHeadersToSheet($sheet, $translatedHeaders);
        $this->writeValuesToSheet($sheet, $this->formatValues($results, $methods));

        return $this->createStreamedResponse($fileName, 'xlsx');
    }

    public function exportCsv(Query $query, array $methods, string $fileName): StreamedResponse
    {
        $results = $this->getResults($query);
        $translatedHeaders = $this->getTranslatedHeaders($methods);

        return new StreamedResponse(function () use ($results, $translatedHeaders, $methods) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $translatedHeaders);

            foreach ($this->formatValues($results, $methods) as $value) {
                fputcsv($handle, $value);
            }

            fclose($handle);
        }, 200, $this->getCsvHeaders($fileName));
    }

    private function getResults(Query $query): array
    {
        $results = $query->getResult();
        if (empty($results)) {
            throw new InvalidArgumentException('There are no results to export');
        }

        return $results;
    }

    private function getTranslatedHeaders(array $methods): array
    {
        return array_map(fn ($method) => $this->translator->trans('import_export.' . $this->methodToSnake->convert($method), [], 'messages'),
            $methods
        );
    }

    private function writeHeadersToSheet($sheet, array $headers): void
    {
        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }
    }

    private function writeValuesToSheet($sheet, array $values): void
    {
        foreach ($values as $row => $value) {
            foreach ($value as $col => $val) {
                $sheet->setCellValueByColumnAndRow($col + 1, $row + 2, $val);
            }
        }
    }

    private function createStreamedResponse(string $fileName, string $format): StreamedResponse
    {
        $response = new StreamedResponse(function () {
            $writer = new Xlsx($this->spreadsheet);
            $writer->save('php://output');
        });

        $headers = $this->getXlsxHeaders($fileName);
        $response->headers->add($headers);

        return $response;
    }

    private function getXlsxHeaders(string $fileName): array
    {
        return [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => sprintf('attachment;filename="%s.xlsx"', $fileName),
            'Cache-Control' => 'max-age=0',
        ];
    }

    private function getCsvHeaders(string $fileName): array
    {
        return [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => sprintf('attachment;filename="%s.csv"', $fileName),
            'Cache-Control' => 'max-age=0',
        ];
    }

    private function formatValues(array $values, array $methods): array
    {
        return array_map(function ($entity) use ($methods) {
            return array_map(function ($method) use ($entity) {
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
            default => (string) $value,
        };
    }
}
