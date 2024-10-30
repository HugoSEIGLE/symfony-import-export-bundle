<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services;

interface MethodToSnakeInterface
{
    public function convert(string $method): string;
}
