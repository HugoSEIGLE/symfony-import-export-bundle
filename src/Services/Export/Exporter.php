<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Services\Export;

use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Query;
use HugoSEIGLE\SymfonyImportExportBundle\Services\MethodToSnakeInterface;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_map;
use function array_values;
use function fclose;
use function fopen;
use function fputcsv;
use function fwrite;
use function get_class;
use function gettype;
use function implode;
use function is_array;
use function is_bool;
use function is_object;
use function is_scalar;
use function method_exists;
use function sprintf;
use function strtolower;

class Exporter implements ExporterInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly MethodToSnakeInterface $methodToSnake,
        private readonly string $dateFormat = 'Y-m-d H:i:s',
        private readonly string $boolTrue = 'true',
        private readonly string $boolFalse = 'false',
        private readonly string $csvDelimiter = ',',
        private readonly string $csvEnclosure = '"',
        private readonly string $csvEscape = '\\',
        private readonly bool $csvBom = false,
    ) {
    }

    public function export(Query $query, array $methods, string $fileName, string $fileType): StreamedResponse
    {
        $methods = array_values($methods);
        $translatedHeaders = $this->getTranslatedHeaders($methods);

        return match (strtolower($fileType)) {
            self::XLSX => $this->exportXlsx($query, $translatedHeaders, $methods, $fileName),
            self::CSV => $this->exportCsv($query, $translatedHeaders, $methods, $fileName),
            default => throw new InvalidArgumentException(sprintf('Unsupported file type %s', $fileType)),
        };
    }

    /** @param list<string> $methods
     * @param list<string> $translatedHeaders
     */
    private function exportXlsx(Query $query, array $translatedHeaders, array $methods, string $fileName): StreamedResponse
    {
        return new StreamedResponse(function () use ($query, $translatedHeaders, $methods): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            foreach ($translatedHeaders as $column => $header) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($column + 1) . '1', $header);
            }

            $row = 2;
            foreach ($query->toIterable() as $entity) {
                if (!is_object($entity)) {
                    throw new InvalidArgumentException('Expected query results to contain objects.');
                }
                foreach ($this->formatEntity($entity, $methods) as $column => $value) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($column + 1) . $row, $value);
                }
                ++$row;
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 200, $this->getXlsxHeaders($fileName));
    }

    /** @param list<string> $methods
     * @param list<string> $translatedHeaders
     */
    private function exportCsv(Query $query, array $translatedHeaders, array $methods, string $fileName): StreamedResponse
    {
        return new StreamedResponse(function () use ($query, $translatedHeaders, $methods): void {
            $handle = fopen('php://output', 'w');
            if (false === $handle) {
                throw new RuntimeException('Could not open output stream.');
            }

            if ($this->csvBom) {
                fwrite($handle, "\xEF\xBB\xBF");
            }
            fputcsv($handle, $translatedHeaders, $this->csvDelimiter, $this->csvEnclosure, $this->csvEscape);

            foreach ($query->toIterable() as $entity) {
                if (!is_object($entity)) {
                    throw new InvalidArgumentException('Expected query results to contain objects.');
                }
                fputcsv($handle, $this->formatEntity($entity, $methods), $this->csvDelimiter, $this->csvEnclosure, $this->csvEscape);
            }

            fclose($handle);
        }, 200, $this->getCsvHeaders($fileName));
    }

    /** @param list<string> $methods
     * @return list<string>
     */
    private function getTranslatedHeaders(array $methods): array
    {
        return array_map(fn (string $method): string => $this->translator->trans('import_export.' . $this->methodToSnake->convert($method), [], 'messages'), $methods);
    }

    /** @return array<string, string> */
    private function getXlsxHeaders(string $fileName): array
    {
        return [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => sprintf('attachment;filename="%s.xlsx"', $fileName),
            'Cache-Control' => 'max-age=0',
        ];
    }

    /** @return array<string, string> */
    private function getCsvHeaders(string $fileName): array
    {
        return [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment;filename="%s.csv"', $fileName),
            'Cache-Control' => 'max-age=0',
        ];
    }

    /** @param list<string> $methods
     * @return list<string>
     */
    private function formatEntity(object $entity, array $methods): array
    {
        return array_map(function (string $method) use ($entity): string {
            if (!method_exists($entity, $method)) {
                throw new InvalidArgumentException(sprintf('Method %s does not exist on entity %s', $method, get_class($entity)));
            }

            return $this->formatValue($entity->$method());
        }, $methods);
    }

    private function formatValue(mixed $value): string
    {
        return match (true) {
            null === $value => '',
            is_bool($value) => $value ? $this->boolTrue : $this->boolFalse,
            is_array($value) => implode(', ', array_map($this->formatValue(...), $value)),
            $value instanceof DateTimeInterface => $value->format($this->dateFormat),
            $value instanceof Collection => implode(', ', array_map($this->formatValue(...), $value->toArray())),
            is_scalar($value) => (string) $value,
            is_object($value) => method_exists($value, '__toString') ? (string) $value : throw new InvalidArgumentException(sprintf('Cannot cast object of class %s to string', get_class($value))),
            default => throw new InvalidArgumentException(sprintf('Cannot cast value of type %s to string', gettype($value))),
        };
    }
}
