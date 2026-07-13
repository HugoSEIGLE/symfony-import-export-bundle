<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Services\Export;

use Doctrine\ORM\Query;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface ExporterInterface
{
    public const XLSX = 'xlsx';
    public const CSV = 'csv';

    /**
     * @param array<string> $methods
     */
    public function export(Query $query, array $methods, string $fileName, string $fileType): StreamedResponse;
}
