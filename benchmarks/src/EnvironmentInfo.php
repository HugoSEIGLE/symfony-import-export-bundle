<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Benchmarks;

use Composer\InstalledVersions;
use Symfony\Component\HttpKernel\Kernel;

use function explode;
use function fclose;
use function fgets;
use function file;
use function function_exists;
use function ini_get;
use function is_array;
use function is_resource;
use function php_uname;
use function proc_close;
use function proc_open;
use function sprintf;
use function str_starts_with;
use function trim;

use const PHP_OS_FAMILY;
use const PHP_VERSION;

final class EnvironmentInfo
{
    /** @return array<string, string> */
    public function collect(string $repositoryRoot): array
    {
        return [
            'php_version' => PHP_VERSION,
            'symfony_version' => Kernel::VERSION,
            'doctrine_orm_version' => $this->packageVersion('doctrine/orm'),
            'phpspreadsheet_version' => $this->packageVersion('phpoffice/phpspreadsheet'),
            'operating_system' => sprintf('%s (%s)', php_uname('s') . ' ' . php_uname('r'), PHP_OS_FAMILY),
            'cpu' => $this->cpu() ?? 'unavailable',
            'memory_limit' => (string) ini_get('memory_limit'),
            'bundle_commit' => $this->commit($repositoryRoot) ?? 'unavailable',
            'peak_reset' => function_exists('memory_reset_peak_usage') ? 'supported' : 'unsupported',
        ];
    }

    private function packageVersion(string $package): string
    {
        return InstalledVersions::isInstalled($package)
            ? (InstalledVersions::getPrettyVersion($package) ?? InstalledVersions::getVersion($package) ?? 'unknown')
            : 'unavailable';
    }

    private function cpu(): ?string
    {
        $lines = @file('/proc/cpuinfo');
        if (!is_array($lines)) {
            return null;
        }
        foreach ($lines as $line) {
            if (str_starts_with($line, 'model name')) {
                $parts = explode(':', $line, 2);

                return isset($parts[1]) ? trim($parts[1]) : null;
            }
        }

        return null;
    }

    private function commit(string $repositoryRoot): ?string
    {
        $pipes = [];
        $process = @proc_open(
            ['git', '-C', $repositoryRoot, 'rev-parse', '--short', 'HEAD'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            return null;
        }

        $output = fgets($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return 0 === $exitCode && false !== $output ? trim($output) : null;
    }
}
