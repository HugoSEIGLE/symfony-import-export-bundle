<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use UnexpectedValueException;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

class SymfonyImportExportExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->setParameter($container, 'import_export.importers', $config['importers']);
        $this->setParameter($container, 'import_export.date_format', $config['date_format']);
        $this->setParameter($container, 'import_export.bool_true', $config['bool_true']);
        $this->setParameter($container, 'import_export.bool_false', $config['bool_false']);
        $this->setParameter($container, 'import_export.validate_headers', $config['validate_headers']);

        $csv = $config['csv'];
        if (!is_array($csv)) {
            throw new UnexpectedValueException('The normalized CSV configuration must be an array.');
        }
        $this->setParameter($container, 'import_export.csv.delimiter', $csv['delimiter'] ?? null);
        $this->setParameter($container, 'import_export.csv.enclosure', $csv['enclosure'] ?? null);
        $this->setParameter($container, 'import_export.csv.escape', $csv['escape'] ?? null);
        $this->setParameter($container, 'import_export.csv.bom', $csv['bom'] ?? null);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }

    private function setParameter(ContainerBuilder $container, string $name, mixed $value): void
    {
        if (!is_array($value) && !is_bool($value) && !is_float($value) && !is_int($value) && !is_string($value) && null !== $value) {
            throw new UnexpectedValueException('Invalid container parameter value for ' . $name . '.');
        }

        $container->setParameter($name, $value);
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
