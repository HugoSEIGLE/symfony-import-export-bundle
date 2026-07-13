<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Services\Import;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface ImporterInterface
{
    public const XLSX = 'xlsx';
    public const CSV = 'csv';

    /**
     * @param class-string $entityClass
     * @param class-string $formType
     * @param bool $allowDelete allow this call to produce deleted entities
     * @param bool $allowCreate allow this call to produce created entities
     * @param bool $allowUpdate allow this call to produce updated entities
     */
    public function import(
        UploadedFile $file,
        string $entityClass,
        string $formType,
        bool $allowDelete = true,
        bool $allowCreate = true,
        bool $allowUpdate = true,
    ): ImportResult;
}
