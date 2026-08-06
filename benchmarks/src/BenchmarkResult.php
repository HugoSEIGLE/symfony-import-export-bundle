<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Benchmarks;

final class BenchmarkResult
{
    public function __construct(
        public readonly string $operation,
        public readonly string $format,
        public readonly int $rows,
        public readonly int $durationNanoseconds,
        public readonly int $peakMemoryBytes,
        public readonly int $runs = 1,
    ) {
    }

    /** @return array{operation: string, format: string, rows: int, runs: int, median_duration_seconds: float, median_peak_memory_mb: float} */
    public function toArray(): array
    {
        return [
            'operation' => $this->operation,
            'format' => $this->format,
            'rows' => $this->rows,
            'runs' => $this->runs,
            'median_duration_seconds' => $this->durationNanoseconds / 1_000_000_000,
            'median_peak_memory_mb' => $this->peakMemoryBytes / 1024 / 1024,
        ];
    }
}
