# Upgrading from 1.x

Version 2 standardizes every public class under:

```text
HugoSEIGLE\SymfonyImportExportBundle\
```

Replace imports beginning with the former `SymfonyImportExportBundle\` prefix, including the bundle entry in `config/bundles.php` and injected service interfaces. Composer autoloading does not provide a compatibility alias for the former namespace.

The import API is now result-based:

```php
$result = $importer->import($file, Entity::class, EntityImportType::class);
$result->getErrors();
$result->getCreatedEntities();
```

The former stateful importer methods `getErrors()`, `isValid()`, `getSummary()`, and `getResult()` are removed. The importer receives normalized configuration through dependency injection rather than reading a parameter bag. The unused `Spreadsheet` constructor argument on the concrete exporter is also removed.

These are breaking changes for direct 1.x API use. Applications injecting the version 2 interfaces and using the returned `ImportResult` need no further migration for 2.0.1.
