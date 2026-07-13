<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Services\Import;

use function count;

final class ImportResult
{
    /** @var list<object> */
    private array $createdEntities = [];

    /** @var list<object> */
    private array $updatedEntities = [];

    /** @var list<object> */
    private array $deletedEntities = [];

    /** @var list<ImportError> */
    private array $errors = [];

    public function addCreatedEntity(object $entity): void
    {
        $this->createdEntities[] = $entity;
    }

    public function addUpdatedEntity(object $entity): void
    {
        $this->updatedEntities[] = $entity;
    }

    public function addDeletedEntity(object $entity): void
    {
        $this->deletedEntities[] = $entity;
    }

    public function addError(ImportError $error): void
    {
        $this->errors[] = $error;
    }

    /** @return list<object> */
    public function getCreatedEntities(): array
    {
        return $this->createdEntities;
    }

    /** @return list<object> */
    public function getUpdatedEntities(): array
    {
        return $this->updatedEntities;
    }

    /** @return list<object> */
    public function getDeletedEntities(): array
    {
        return $this->deletedEntities;
    }

    /** @return list<ImportError> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function isValid(): bool
    {
        return [] === $this->errors;
    }

    public function getErrorCount(): int
    {
        return count($this->errors);
    }
}
