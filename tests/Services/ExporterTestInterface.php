<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Tests\Services;

use PHPUnit\Framework\TestCase;

interface ExporterTestInterface
{
    public function testExportToXlsxGeneratesCorrectResponse(): void;

    public function testExportToCsvGeneratesCorrectResponse(): void;
}
