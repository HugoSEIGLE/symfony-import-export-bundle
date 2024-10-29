<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Tests\Services;

interface ImporterTestInterface
{
    public function testImportXlsxFile(): void;

    public function testImportCsvFile(): void;
}
