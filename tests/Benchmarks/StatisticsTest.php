<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Tests\Benchmarks;

use HugoSEIGLE\SymfonyImportExportBundle\Benchmarks\Statistics;
use PHPUnit\Framework\TestCase;

final class StatisticsTest extends TestCase
{
    public function testMedianIsIndependentFromInputOrder(): void
    {
        self::assertSame(20, (new Statistics())->median([30, 10, 20]));
    }

    public function testMedianAveragesTwoCentralValuesForEvenSamples(): void
    {
        self::assertSame(25, (new Statistics())->median([40, 10, 30, 20]));
    }
}
