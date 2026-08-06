<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Benchmarks;

use InvalidArgumentException;

use function array_shift;
use function end;
use function explode;
use function implode;
use function in_array;
use function preg_match;
use function sprintf;
use function strtolower;

final class BenchmarkOptions
{
    /**
     * @param list<int> $rows
     * @param list<string> $formats
     * @param list<string> $operations
     */
    private function __construct(
        public readonly array $rows,
        public readonly array $formats,
        public readonly array $operations,
        public readonly int $runs,
        public readonly ?string $output,
        public readonly bool $help,
    ) {
    }

    /** @param list<string> $arguments */
    public static function parse(array $arguments): self
    {
        array_shift($arguments);
        $rows = [100, 1000, 10000, 50000];
        $formats = ['csv', 'xlsx'];
        $operations = ['import', 'export'];
        $runs = 3;
        $output = null;
        $help = false;

        foreach ($arguments as $argument) {
            if ('--help' === $argument || '-h' === $argument) {
                $help = true;
                continue;
            }

            if (1 !== preg_match('/^--([a-z-]+)=(.+)$/', $argument, $matches)) {
                throw new InvalidArgumentException(sprintf('Unknown option "%s".', $argument));
            }

            switch ($matches[1]) {
                case 'rows':
                    $rows = self::parseRows($matches[2]);
                    break;
                case 'format':
                    $formats = self::parseSelection($matches[2], ['csv', 'xlsx'], 'format');
                    break;
                case 'operation':
                    $operations = self::parseSelection($matches[2], ['import', 'export'], 'operation');
                    break;
                case 'runs':
                    if (1 !== preg_match('/^[1-9][0-9]*$/', $matches[2])) {
                        throw new InvalidArgumentException('Runs must be a positive integer.');
                    }
                    $runs = (int) $matches[2];
                    break;
                case 'output':
                    if (!in_array(self::extension($matches[2]), ['json', 'csv'], true)) {
                        throw new InvalidArgumentException('The output file must use the .json or .csv extension.');
                    }
                    $output = $matches[2];
                    break;
                default:
                    throw new InvalidArgumentException(sprintf('Unknown option "--%s".', $matches[1]));
            }
        }

        return new self($rows, $formats, $operations, $runs, $output, $help);
    }

    /** @return list<int> */
    private static function parseRows(string $value): array
    {
        $rows = [];
        foreach (explode(',', $value) as $item) {
            if (1 !== preg_match('/^[1-9][0-9]*$/', $item)) {
                throw new InvalidArgumentException('Rows must be positive integers separated by commas.');
            }
            $rows[] = (int) $item;
        }

        return $rows;
    }

    /**
     * @param list<string> $allowed
     *
     * @return list<string>
     */
    private static function parseSelection(string $value, array $allowed, string $name): array
    {
        if ('all' === $value) {
            return $allowed;
        }
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('Invalid %s "%s". Expected %s or all.', $name, $value, implode(', ', $allowed)));
        }

        return [$value];
    }

    private static function extension(string $path): string
    {
        $parts = explode('.', $path);

        return strtolower((string) end($parts));
    }
}
