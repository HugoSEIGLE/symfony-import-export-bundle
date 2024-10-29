<?php

namespace SymfonyImportExportBundle\Services;

use Doctrine\ORM\QueryBuilder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface ExporterInterface
{
    public function export(QueryBuilder $queryBuilder, array $fields): StreamedResponse;
}
