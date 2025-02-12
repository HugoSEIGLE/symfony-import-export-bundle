<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services;

use function preg_replace;
use function strtolower;

class MethodToSnake implements MethodToSnakeInterface
{
    public function convert(string $method): string
    {
        $converted = preg_replace('/([a-z])([A-Z])/', '$1_$2', $method);

        return strtolower($converted ?? '');
    }
}
