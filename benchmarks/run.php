<?php

declare(strict_types=1);

use HugoSEIGLE\SymfonyImportExportBundle\Benchmarks\BenchmarkOptions;
use HugoSEIGLE\SymfonyImportExportBundle\Benchmarks\BenchmarkRunner;
use HugoSEIGLE\SymfonyImportExportBundle\Benchmarks\EnvironmentInfo;
use HugoSEIGLE\SymfonyImportExportBundle\Benchmarks\ResultFormatter;
use HugoSEIGLE\SymfonyImportExportBundle\Benchmarks\ResultWriter;

require \dirname(__DIR__) . '/vendor/autoload.php';

$usage = <<<'TEXT'
Symfony Import Export Bundle benchmark

Usage:
  php benchmarks/run.php [options]

Options:
  --rows=1000             Row count, or a comma-separated list (default: 100,1000,10000,50000)
  --format=csv|xlsx|all   Select a format (default: all)
  --operation=import|export|all
                          Select an operation (default: all)
  --runs=3                Isolated runs per scenario used for medians (default: 3)
  --output=results.json   Save results as JSON or CSV
  --help                  Show this help
TEXT;

try {
    $options = BenchmarkOptions::parse($argv);
    if ($options->help) {
        echo $usage . "\n";
        exit(0);
    }

    $environment = (new EnvironmentInfo())->collect(\dirname(__DIR__));
    echo "Environment\n";
    foreach ($environment as $name => $value) {
        echo \sprintf("  %-24s %s\n", \str_replace('_', ' ', \ucfirst($name)) . ':', $value);
    }

    echo "\nMethodology\n";
    echo \sprintf("  %-24s %d\n", 'Isolated runs:', $options->runs);
    echo \sprintf("  %-24s %s\n", 'Aggregation:', 'median time and median peak memory');
    echo \sprintf("  %-24s %s\n", 'Dataset setup:', 'excluded from measurements');

    echo "\nRunning benchmarks...\n";
    $results = (new BenchmarkRunner())->run($options);
    echo "\n" . (new ResultFormatter())->terminal($results) . "\n";

    if (null !== $options->output) {
        (new ResultWriter())->write($options->output, $environment, $results);
        echo \sprintf("\nResults written to %s.\n", $options->output);
    }
} catch (Throwable $exception) {
    \fwrite(\STDERR, 'Benchmark failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
