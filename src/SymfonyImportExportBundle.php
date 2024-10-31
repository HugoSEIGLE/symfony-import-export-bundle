<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle;

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use SymfonyImportExportBundle\DependencyInjection\SymfonyImportExportExtension;

class SymfonyImportExportBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        if (null === $this->extension || false === $this->extension) {
            $this->extension = new SymfonyImportExportExtension();
        }

        return $this->extension;
    }
}
