<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface ExporterInterface
{
    public function export(QueryBuilder $queryBuilder, array $fields): StreamedResponse;
}
