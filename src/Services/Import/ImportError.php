<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Services\Import;

final class ImportError
{
    public function __construct(
        public readonly int $row,
        public readonly ?string $field,
        public readonly string $message,
        public readonly mixed $value = null,
    ) {
    }
}
