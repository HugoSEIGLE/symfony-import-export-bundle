<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services\Import;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface ImporterInterface
{
    public const string XLSX = 'xlsx';
    public const string CSV = 'csv';

    public function import(UploadedFile $file, string $entityClass, string $formType): void;

    /**
     * @return array<string>
     */
    public function getErrors(): array;

    public function isValid(): bool;

    /**
     * @return array<string>
     */
    public function getSummary(): array;
}
