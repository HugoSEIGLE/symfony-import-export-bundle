<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Tests\DependencyInjection;

use HugoSEIGLE\SymfonyImportExportBundle\DependencyInjection\SymfonyImportExportExtension;
use HugoSEIGLE\SymfonyImportExportBundle\Services\Export\Exporter;
use HugoSEIGLE\SymfonyImportExportBundle\Services\Export\ExporterInterface;
use HugoSEIGLE\SymfonyImportExportBundle\Services\Import\Importer;
use HugoSEIGLE\SymfonyImportExportBundle\Services\Import\ImporterInterface;
use HugoSEIGLE\SymfonyImportExportBundle\Services\Import\ImporterTemplate;
use HugoSEIGLE\SymfonyImportExportBundle\Services\Import\ImporterTemplateInterface;
use HugoSEIGLE\SymfonyImportExportBundle\SymfonyImportExportBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

final class BundleTest extends TestCase
{
    public function testBundleExposesCanonicalExtension(): void
    {
        $extension = (new SymfonyImportExportBundle())->getContainerExtension();

        self::assertInstanceOf(SymfonyImportExportExtension::class, $extension);
        self::assertSame('import_export', $extension->getAlias());
    }

    public function testDefaultConfigurationAndServicesLoad(): void
    {
        $container = new ContainerBuilder();
        (new SymfonyImportExportExtension())->load([], $container);

        self::assertSame([], $container->getParameter('import_export.importers'));
        self::assertSame('Y-m-d H:i:s', $container->getParameter('import_export.date_format'));
        self::assertTrue($container->getParameter('import_export.validate_headers'));
        self::assertSame(Exporter::class, (string) $container->getAlias(ExporterInterface::class));
        self::assertSame(Importer::class, (string) $container->getAlias(ImporterInterface::class));
        self::assertSame(ImporterTemplate::class, (string) $container->getAlias(ImporterTemplateInterface::class));
        self::assertTrue($container->getDefinition(Exporter::class)->isAutowired());
        self::assertTrue($container->getDefinition(Importer::class)->isAutowired());
        self::assertTrue($container->getDefinition(ImporterTemplate::class)->isAutowired());
    }

    public function testConfigurationNormalizesAnImporter(): void
    {
        $configuration = new \HugoSEIGLE\SymfonyImportExportBundle\DependencyInjection\Configuration();
        $config = (new Processor())->processConfiguration($configuration, [[
            'importers' => [
                'App\\Entity\\Company' => [
                    'fields' => ['name', 'email'],
                    'unique_fields' => ['email'],
                    'allow_delete' => true,
                ],
            ],
        ]]);

        self::assertSame(['name', 'email'], $config['importers']['App\\Entity\\Company']['fields']);
        self::assertSame(['email'], $config['importers']['App\\Entity\\Company']['unique_fields']);
        self::assertTrue($config['importers']['App\\Entity\\Company']['allow_delete']);
    }

    public function testImporterCsrfOptionIsAvailable(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension(new CsrfTokenManager()))
            ->getFormFactory();

        $form = $factory->createNamed('import', FormType::class, null, ['csrf_protection' => false]);

        self::assertFalse($form->getConfig()->getOption('csrf_protection'));
    }
}
