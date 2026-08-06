<?php

declare(strict_types=1);

use HugoSEIGLE\SymfonyImportExportBundle\Benchmarks\BenchmarkRunner;

require \dirname(__DIR__) . '/vendor/autoload.php';

try {
    $operation = $argv[1] ?? '';
    $format = $argv[2] ?? '';
    $rows = \filter_var($argv[3] ?? null, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!\in_array($operation, ['import', 'export'], true) || !\in_array($format, ['csv', 'xlsx'], true) || false === $rows) {
        throw new InvalidArgumentException('Invalid isolated benchmark scenario.');
    }

    $result = (new BenchmarkRunner())->runScenario($operation, $format, $rows);
    echo \json_encode([
        'operation' => $result->operation,
        'format' => $result->format,
        'rows' => $result->rows,
        'duration_nanoseconds' => $result->durationNanoseconds,
        'peak_memory_bytes' => $result->peakMemoryBytes,
    ], \JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    \fwrite(\STDERR, $exception->getMessage() . "\n");
    exit(1);
}
