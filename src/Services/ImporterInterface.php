<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface ImporterInterface
{
    public function import(UploadedFile $file, string $entityClass, string $formType): void;

    public function isValid(): bool;

    public function getErrors(): array;
}
