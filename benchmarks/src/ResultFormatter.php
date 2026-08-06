<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Benchmarks;

use function array_map;
use function implode;
use function max;
use function number_format;
use function sprintf;
use function strlen;
use function strtoupper;
use function ucfirst;

final class ResultFormatter
{
    /** @param list<BenchmarkResult> $results */
    public function terminal(array $results): string
    {
        $rows = [['Operation', 'Format', 'Rows', 'Median time', 'Peak memory']];
        foreach ($results as $result) {
            $rows[] = [
                ucfirst($result->operation),
                strtoupper($result->format),
                number_format($result->rows),
                sprintf('%.3f s', $result->durationNanoseconds / 1_000_000_000),
                sprintf('%.1f MB', $result->peakMemoryBytes / 1024 / 1024),
            ];
        }

        $widths = [0, 0, 0, 0, 0];
        foreach ($rows as $row) {
            foreach ($row as $column => $value) {
                $widths[$column] = max($widths[$column], strlen($value));
            }
        }

        return implode("\n", array_map(static fn (array $row): string => sprintf(
            "%-{$widths[0]}s  %-{$widths[1]}s  %{$widths[2]}s  %{$widths[3]}s  %{$widths[4]}s",
            ...$row,
        ), $rows));
    }
}
