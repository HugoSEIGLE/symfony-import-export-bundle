# Installation

## Requirements

- PHP 8.1 or newer
- Symfony 6.4, 7.x or 8.x
- Doctrine ORM 3.2 or newer within the supported 3.x line
- PhpSpreadsheet 2.3.5 or newer within the supported 2.x line

Install the bundle as a production dependency:

```bash
composer require hugoseigle/symfony-import-export-bundle
```

If Symfony Flex does not enable it, add the canonical bundle class manually:

```php
// config/bundles.php
return [
    HugoSEIGLE\SymfonyImportExportBundle\SymfonyImportExportBundle::class => ['all' => true],
];
```

Minimal configuration:

```yaml
# config/packages/import_export.yaml
import_export:
    importers:
        App\Entity\Company:
            fields: [name, email]
            unique_fields: [email]
```

The configuration key is `import_export`. The bundle registers `ExporterInterface`, `ImporterInterface`, `ImporterTemplateInterface`, and `MethodToSnakeInterface` for autowiring.
