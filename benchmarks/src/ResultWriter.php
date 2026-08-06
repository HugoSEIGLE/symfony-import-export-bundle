<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Benchmarks;

use JsonException;
use RuntimeException;

use function array_keys;
use function array_map;
use function array_values;
use function fclose;
use function file_put_contents;
use function fopen;
use function fputcsv;
use function json_encode;
use function sprintf;
use function str_ends_with;
use function strtolower;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

final class ResultWriter
{
    /**
     * @param array<string, string> $environment
     * @param list<BenchmarkResult> $results
     *
     * @throws JsonException
     */
    public function write(string $path, array $environment, array $results): void
    {
        if (str_ends_with(strtolower($path), '.json')) {
            $this->writeJson($path, $environment, $results);

            return;
        }

        $this->writeCsv($path, $environment, $results);
    }

    /** @param array<string, string> $environment
     * @param list<BenchmarkResult> $results
     *
     * @throws JsonException
     */
    private function writeJson(string $path, array $environment, array $results): void
    {
        $json = json_encode([
            'environment' => $environment,
            'results' => array_map(static fn (BenchmarkResult $result): array => $result->toArray(), $results),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (false === file_put_contents($path, $json . "\n")) {
            throw new RuntimeException(sprintf('Unable to write %s.', $path));
        }
    }

    /** @param array<string, string> $environment
     * @param list<BenchmarkResult> $results
     */
    private function writeCsv(string $path, array $environment, array $results): void
    {
        $handle = fopen($path, 'wb');
        if (false === $handle) {
            throw new RuntimeException(sprintf('Unable to write %s.', $path));
        }

        try {
            $resultHeaders = ['operation', 'format', 'rows', 'runs', 'median_duration_seconds', 'median_peak_memory_mb'];
            fputcsv($handle, [...$resultHeaders, ...array_keys($environment)], ',', '"', '');
            foreach ($results as $result) {
                fputcsv($handle, [...array_values($result->toArray()), ...array_values($environment)], ',', '"', '');
            }
        } finally {
            fclose($handle);
        }
    }
}
