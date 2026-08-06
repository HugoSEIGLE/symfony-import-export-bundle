<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Tests\Benchmarks;

use HugoSEIGLE\SymfonyImportExportBundle\Benchmarks\TemporaryWorkspace;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_dir;
use function is_file;

final class TemporaryWorkspaceTest extends TestCase
{
    public function testCleanupRemovesGeneratedFilesAndDirectory(): void
    {
        $workspace = TemporaryWorkspace::create();
        $file = $workspace->file('temporary.csv');
        $secondFile = $workspace->file('temporary.sqlite');
        file_put_contents($file, 'data');
        file_put_contents($secondFile, 'data');

        self::assertFileExists($file);
        self::assertFileExists($secondFile);
        $workspace->cleanup();

        self::assertFalse(is_file($file));
        self::assertFalse(is_file($secondFile));
        self::assertFalse(is_dir($workspace->path));
    }
}
