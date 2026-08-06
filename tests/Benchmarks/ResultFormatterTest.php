<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Tests\Benchmarks;

use HugoSEIGLE\SymfonyImportExportBundle\Benchmarks\BenchmarkResult;
use HugoSEIGLE\SymfonyImportExportBundle\Benchmarks\ResultFormatter;
use PHPUnit\Framework\TestCase;

final class ResultFormatterTest extends TestCase
{
    public function testTerminalOutputContainsHumanReadableMeasurements(): void
    {
        $output = (new ResultFormatter())->terminal([
            new BenchmarkResult('import', 'csv', 1000, 420_000_000, 19_503_514),
        ]);

        self::assertStringContainsString('Operation', $output);
        self::assertStringContainsString('Import', $output);
        self::assertStringContainsString('CSV', $output);
        self::assertStringContainsString('1,000', $output);
        self::assertStringContainsString('0.420 s', $output);
        self::assertStringContainsString('18.6 MB', $output);
    }
}
