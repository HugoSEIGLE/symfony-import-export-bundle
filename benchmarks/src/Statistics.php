<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Benchmarks;

use InvalidArgumentException;

use function count;
use function intdiv;
use function sort;

final class Statistics
{
    /** @param list<int> $values */
    public function median(array $values): int
    {
        if ([] === $values) {
            throw new InvalidArgumentException('A median requires at least one value.');
        }

        sort($values);
        $middle = intdiv(count($values), 2);
        if (1 === count($values) % 2) {
            return $values[$middle];
        }

        return intdiv($values[$middle - 1] + $values[$middle], 2);
    }
}
