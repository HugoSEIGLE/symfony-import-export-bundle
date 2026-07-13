[![Packagist Version](https://img.shields.io/packagist/v/hugoseigle/symfony-import-export-bundle)](https://packagist.org/packages/hugoseigle/symfony-import-export-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/hugoseigle/symfony-import-export-bundle)](https://packagist.org/packages/hugoseigle/symfony-import-export-bundle)

# Symfony ImportExportBundle

Import and export Doctrine entities as CSV or XLSX using Symfony forms for import validation. The bundle supports PHP 8.1+, Symfony 6.4/7/8, Doctrine ORM 3 and PhpSpreadsheet 2.

## Installation

```bash
composer require hugoseigle/symfony-import-export-bundle
```

If Symfony Flex does not register the bundle automatically, add it to `config/bundles.php`:

```php
return [
    SymfonyImportExportBundle\SymfonyImportExportBundle::class => ['all' => true],
];
```

## Configuration

Create `config/packages/import_export.yaml`:

```yaml
import_export:
  date_format: 'Y-m-d H:i:s'
  bool_true: 'yes'
  bool_false: 'no'
  validate_headers: true

  csv:
    delimiter: ','
    enclosure: '"'
    escape: '\'
    bom: false

  importers:
    App\Entity\Product:
      fields: [sku, name, price, active, availableAt, category, tags]
      unique_fields: [sku]
      allow_delete: true
      # Optional per-entity override:
      # validate_headers: false
```

The order of `fields` is significant. With strict header validation enabled (the default), the file must use exactly this order. Headers may be either the field names or the complete translated header list produced by the template generator. When `allow_delete` is enabled, a final `deleted` column is required.

`bool_true` and `bool_false` are matched case-insensitively after trimming. Any other value is reported as an import error. CSV controls are shared by import, export and template generation; `delimiter` and `enclosure` must be one character and `escape` must be zero or one character.

## Importing

Create a Symfony form type containing every configured field. Standard form constraints and data transformers remain the source of validation for entity data and Doctrine relations.

```php
use App\Entity\Product;
use App\Form\ProductImportType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use SymfonyImportExportBundle\Services\Import\ImporterInterface;

public function import(
    Request $request,
    ImporterInterface $importer,
    EntityManagerInterface $entityManager,
): Response {
    $file = $request->files->get('import_file');
    $result = $importer->import($file, Product::class, ProductImportType::class);

    if (!$result->isValid()) {
        foreach ($result->getErrors() as $error) {
            // $error->row, $error->field, $error->message, $error->value
        }

        return new Response('The import contains errors.', Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    foreach ($result->getCreatedEntities() as $entity) {
        $entityManager->persist($entity);
    }

    // Updated entities are already managed when loaded by Doctrine. Calling
    // persist() is normally unnecessary, but the list is available for auditing.
    foreach ($result->getDeletedEntities() as $entity) {
        $entityManager->remove($entity);
    }

    $entityManager->flush();

    return new Response(sprintf(
        'Import complete: %d created, %d updated, %d deleted.',
        count($result->getCreatedEntities()),
        count($result->getUpdatedEntities()),
        count($result->getDeletedEntities()),
    ));
}
```

Each data row is independent: malformed rows add structured `ImportError` objects and do not prevent later rows from being processed. The result contains candidate changes only; the bundle deliberately does not call `persist()`, `remove()` or `flush()`, so the application controls transactions and partial-import policy.

### Backward-compatible accessors

The stateful accessors remain temporarily available:

```php
$importer->getErrors();  // list<string>, deprecated
$importer->isValid();    // deprecated
$importer->getSummary(); // ['created' => [...], 'updated' => [...], 'deleted' => [...]], deprecated
$importer->getResult();  // the latest ImportResult
```

They are reset at the beginning of every `import()` call. New code should retain the returned `ImportResult` instead.

## Exporting

Pass a Doctrine ORM `Query`, getter methods in column order, a base filename, and a type:

```php
use SymfonyImportExportBundle\Services\Export\ExporterInterface;

public function export(ExporterInterface $exporter): Response
{
    $query = $this->productRepository->createExportQuery();

    return $exporter->export(
        $query,
        ['getSku', 'getName', 'getPrice', 'isActive', 'getAvailableAt'],
        'products',
        ExporterInterface::CSV, // or ExporterInterface::XLSX
    );
}
```

CSV rows are written directly from `Query::toIterable()` and are not accumulated in memory. XLSX also iterates the query, but PhpSpreadsheet still builds the workbook in memory. Empty queries produce a valid file containing headers only. Every XLSX export receives a fresh `Spreadsheet`, so repeated exports cannot leak worksheet state.

Headers use translation keys derived from method names, for example `getAvailableAt` becomes `import_export.get_available_at` in the `messages` domain.

## Import templates

```php
use App\Entity\Product;
use SymfonyImportExportBundle\Services\Import\ImporterInterface;
use SymfonyImportExportBundle\Services\Import\ImporterTemplateInterface;

public function template(ImporterTemplateInterface $templates): Response
{
    return $templates->getImportTemplate(Product::class, ImporterInterface::XLSX);
}
```

CSV and XLSX templates contain the configured headers, including `deleted` when deletion is enabled.

## Supported Doctrine values

- Scalars and booleans.
- `date`, `datetime`, `date_immutable` and `datetime_immutable`, parsed strictly with `date_format`.
- Backed enums declared through Doctrine's `enumType` mapping.
- To-one associations as a single submitted value.
- To-many associations as comma-separated submitted values.

Association values are passed to the Symfony form. Configure an appropriate form field/data transformer (commonly `EntityType`) to resolve identifiers into entities.

## Limitations

- XLSX generation is not constant-memory because PhpSpreadsheet holds workbook cells in memory.
- Import previews do not create a Doctrine transaction. The calling application decides whether errors reject the whole file or whether valid candidate changes are persisted.
- CSV is intended for UTF-8 input. A UTF-8 BOM on the first header is accepted; output BOM is configurable.
- Collection CSV values use commas internally, so the CSV cell itself must be correctly quoted.
- Formula evaluation is not performed during optimized XLSX reads; stored cell values are imported.

## Best practices

- Keep `validate_headers: true` in production and generate files from the provided template.
- Use stable database identifiers in `unique_fields`; an empty list always creates new candidates and never runs `findOneBy([])`.
- Wrap persistence/removal and `flush()` in an application transaction when imports must be atomic.
- Validate upload size and MIME type at the HTTP boundary and keep the filename extension consistent with the content.
- Use dedicated import form types with explicit constraints and relation transformers.
- Log structured errors without exposing sensitive cell values.

## Performance

- Prefer CSV for very large exports and imports: both paths stream row by row.
- Keep export queries scalar-light and avoid getter methods that trigger N+1 lazy-loading; join required relations in the query.
- Process large persistence batches in application code and periodically `flush()`/`clear()` when atomicity is not required.
- XLSX reading uses data-only mode and releases worksheets after iteration, but large workbooks still require substantially more memory than CSV.

## Compatibility and migration

PHP 8.1 is the minimum required by the source and the lowest supported releases of Doctrine ORM 3, PhpSpreadsheet 2, PHPUnit 10 and Symfony 6.4. Symfony 7 and 8 are selected by Composer only on PHP versions satisfying their own constraints (Symfony 8 currently requires a newer PHP runtime).

The only source-level API evolution is that `ImporterInterface::import()` now returns `ImportResult` instead of `void`. Existing callers that ignore the return value continue to work. Custom third-party implementations of `ImporterInterface` must update their return type and implement `getResult()`. The legacy result accessors remain available and deprecated to ease migration.

## Development

```bash
composer test
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/grumphp run
```
