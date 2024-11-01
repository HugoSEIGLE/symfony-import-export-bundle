<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class SymfonyImportExportExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('import_export.importers', $config['importers']);
        $container->setParameter('import_export.date_format', $config['date_format']);
        $container->setParameter('import_export.bool_true', $config['bool_true']);
        $container->setParameter('import_export.bool_false', $config['bool_false']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }

    public function getAlias(): string
    {
        return 'import_export';
    }

    public function getXsdValidationBasePath(): string
    {
        return 'https://raw.githubusercontent.com/HugoSEIGLE/symfony-import-export-bundle/refs/heads/main/src/Resources/config/schema';
    }

    public function getNamespace(): string
    {
        return $this->getXsdValidationBasePath();
    }
}
