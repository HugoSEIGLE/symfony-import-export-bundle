<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services\Import;

use Symfony\Component\HttpFoundation\StreamedResponse;

interface ImporterTemplateInterface
{
    public const string XLSX = 'xlsx';
    public const string CSV = 'csv';

    public function getImportTemplate(string $class, string $fileType): StreamedResponse;
}
