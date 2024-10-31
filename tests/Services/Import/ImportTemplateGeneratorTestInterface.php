<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Tests\Services\Import;

interface ImportTemplateGeneratorTestInterface
{
    public function testGetImportTemplateXlsx(): void;
    public function testGetImportTemplateCsv(): void;
}
