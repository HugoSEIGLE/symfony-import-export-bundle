<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services\Export;

use Doctrine\ORM\Query;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface ExporterInterface
{
    /**
     * @param array<string> $methods
     */
    public function exportXlsx(Query $query, array $methods, string $fileName): StreamedResponse;

    /**
     * @param array<string> $methods
     */
    public function exportCsv(Query $query, array $methods, string $fileName): StreamedResponse;
}
