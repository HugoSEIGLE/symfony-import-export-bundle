<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services\Import;

use function sprintf;

final class ImportError
{
    public function __construct(
        public readonly int $row,
        public readonly ?string $field,
        public readonly string $message,
        public readonly mixed $value = null,
    ) {
    }

    public function __toString(): string
    {
        if (null === $this->field || '' === $this->field) {
            return sprintf('Row %d: %s', $this->row, $this->message);
        }

        return sprintf('Row %d: %s (%s)', $this->row, $this->message, $this->field);
    }
}
