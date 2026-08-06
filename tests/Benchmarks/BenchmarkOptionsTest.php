<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Tests\Benchmarks;

use HugoSEIGLE\SymfonyImportExportBundle\Benchmarks\BenchmarkOptions;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BenchmarkOptionsTest extends TestCase
{
    public function testDefaultsSelectAllScenariosAndStandardRowCounts(): void
    {
        $options = BenchmarkOptions::parse(['benchmarks/run.php']);

        self::assertSame([100, 1000, 10000, 50000], $options->rows);
        self::assertSame(['csv', 'xlsx'], $options->formats);
        self::assertSame(['import', 'export'], $options->operations);
        self::assertSame(3, $options->runs);
        self::assertNull($options->output);
    }

    public function testSelectionsAndOutputAreParsed(): void
    {
        $options = BenchmarkOptions::parse([
            'benchmarks/run.php',
            '--rows=10,20',
            '--format=csv',
            '--operation=export',
            '--runs=5',
            '--output=var/results.json',
        ]);

        self::assertSame([10, 20], $options->rows);
        self::assertSame(['csv'], $options->formats);
        self::assertSame(['export'], $options->operations);
        self::assertSame(5, $options->runs);
        self::assertSame('var/results.json', $options->output);
    }

    public function testInvalidRowsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rows must be positive integers');

        BenchmarkOptions::parse(['benchmarks/run.php', '--rows=0']);
    }

    public function testInvalidRunsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Runs must be a positive integer');

        BenchmarkOptions::parse(['benchmarks/run.php', '--runs=0']);
    }
}
