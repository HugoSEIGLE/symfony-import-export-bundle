<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Benchmarks;

use Symfony\Contracts\Translation\TranslatorInterface;

final class BenchmarkTranslator implements TranslatorInterface
{
    /** @param array<string, mixed> $parameters */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $id;
    }

    public function getLocale(): string
    {
        return 'en';
    }
}
