<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services;

use Doctrine\ORM\Query;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface ExporterInterface
{
    public function exportXlsx(Query $query, array $fields, string $fileName): StreamedResponse;

    public function exportCsv(Query $query, array $fields, string $fileName): StreamedResponse;
}
