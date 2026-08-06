<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Benchmarks;

use FilesystemIterator;
use RuntimeException;
use SplFileInfo;

use function bin2hex;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

final class TemporaryWorkspace
{
    private function __construct(public readonly string $path)
    {
    }

    public static function create(?string $parent = null): self
    {
        $parent ??= sys_get_temp_dir();
        $path = $parent . '/symfony-import-export-benchmark-' . bin2hex(random_bytes(8));
        if (!mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Unable to create temporary directory %s.', $path));
        }

        return new self($path);
    }

    public function file(string $name): string
    {
        return $this->path . '/' . $name;
    }

    public function cleanup(): void
    {
        if (!is_dir($this->path)) {
            return;
        }

        $files = [];
        foreach (new FilesystemIterator($this->path) as $file) {
            if (!$file instanceof SplFileInfo) {
                throw new RuntimeException('Unable to inspect a benchmark temporary file.');
            }
            if ($file->isDir()) {
                throw new RuntimeException('The benchmark workspace must not contain nested directories.');
            }
            $files[] = $file->getPathname();
        }
        foreach ($files as $file) {
            if (!unlink($file)) {
                throw new RuntimeException(sprintf('Unable to remove temporary file %s.', $file));
            }
        }
        if (!rmdir($this->path)) {
            throw new RuntimeException(sprintf('Unable to remove temporary directory %s.', $this->path));
        }
    }
}
