<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services\Import;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface ImporterInterface
{
    public const XLSX = 'xlsx';
    public const CSV = 'csv';

    /**
     * @param class-string $entityClass
     * @param class-string $formType
     */
    public function import(UploadedFile $file, string $entityClass, string $formType): ImportResult;

    /**
     * @return array<string>
     */
    public function getErrors(): array;

    public function isValid(): bool;

    public function getResult(): ImportResult;

    /**
     * @return array<mixed>
     */
    public function getSummary(): array;
}
