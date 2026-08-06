<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Tests\Benchmarks;

use HugoSEIGLE\SymfonyImportExportBundle\Benchmarks\BenchmarkResult;
use HugoSEIGLE\SymfonyImportExportBundle\Benchmarks\ResultWriter;
use HugoSEIGLE\SymfonyImportExportBundle\Benchmarks\TemporaryWorkspace;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final class ResultWriterTest extends TestCase
{
    public function testJsonOutputContainsEnvironmentAndMeasurements(): void
    {
        $workspace = TemporaryWorkspace::create();

        try {
            $path = $workspace->file('results.json');
            (new ResultWriter())->write(
                $path,
                ['php_version' => '8.test'],
                [new BenchmarkResult('export', 'xlsx', 100, 250_000_000, 10_485_760)],
            );

            $contents = file_get_contents($path);
            self::assertNotFalse($contents);
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

            self::assertSame('8.test', $data['environment']['php_version']);
            self::assertSame('export', $data['results'][0]['operation']);
            self::assertSame(1, $data['results'][0]['runs']);
            self::assertSame(0.25, $data['results'][0]['median_duration_seconds']);
            self::assertSame(10, $data['results'][0]['median_peak_memory_mb']);
        } finally {
            $workspace->cleanup();
        }
    }
}
