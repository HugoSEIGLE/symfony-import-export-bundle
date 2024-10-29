<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface ExporterInterface
{
    public function exportXlsx(QueryBuilder $queryBuilder, array $fields, string $fileName): StreamedResponse;

    public function exportCsv(QueryBuilder $queryBuilder, array $fields, string $fileName): StreamedResponse;
}
