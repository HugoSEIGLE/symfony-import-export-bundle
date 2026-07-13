[![Packagist Version](https://img.shields.io/packagist/v/hugoseigle/symfony-import-export-bundle)](https://packagist.org/packages/hugoseigle/symfony-import-export-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/hugoseigle/symfony-import-export-bundle)](https://packagist.org/packages/hugoseigle/symfony-import-export-bundle)

# Symfony ImportExportBundle 2

Import and export Doctrine entities as CSV or XLSX using Symfony forms for import validation. The bundle supports PHP 8.1+, Symfony 6.4/7/8, Doctrine ORM 3 and PhpSpreadsheet 2.

## Installation

```bash
composer require hugoseigle/symfony-import-export-bundle:^2.0
```

If Symfony Flex does not register the bundle automatically, add it to `config/bundles.php`:

```php
return [
    HugoSEIGLE\SymfonyImportExportBundle\SymfonyImportExportBundle::class => ['all' => true],
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

Create a dedicated Symfony form type containing every configured entity field. Standard Symfony constraints and form data transformers remain the source of validation for entity data and Doctrine relations. The virtual `deleted` column must not be added to the form: the importer consumes it before submitting the entity data.

```php
// src/Form/ProductImportType.php
namespace App\Form;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Tag;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('sku')
            ->add('name')
            ->add('price')
            ->add('active', CheckboxType::class, ['required' => false])
            ->add('availableAt')
            ->add('category', EntityType::class, [
                'class' => Category::class,
            ])
            ->add('tags', EntityType::class, [
                'class' => Tag::class,
                'multiple' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Product::class]);
    }
}
```

The following is a complete Symfony controller. Runtime operation flags can come directly from voters, roles, the current route or any application rule:

```php
// src/Controller/ProductImportController.php
namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductImportType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use HugoSEIGLE\SymfonyImportExportBundle\Services\Import\ImportError;
use HugoSEIGLE\SymfonyImportExportBundle\Services\Import\ImporterInterface;

final class ProductImportController extends AbstractController
{
    #[Route('/admin/products/import', name: 'app_product_import', methods: ['POST'])]
    public function __invoke(
        Request $request,
        ImporterInterface $importer,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException('A file is required.');
        }

        $result = $importer->import(
            $file,
            Product::class,
            ProductImportType::class,
            allowDelete: $this->isGranted('PRODUCT_DELETE'),
            allowCreate: $this->isGranted('PRODUCT_CREATE'),
            allowUpdate: $this->isGranted('PRODUCT_UPDATE'),
        );

        if (!$result->isValid()) {
            return $this->json([
                'errors' => array_map(static fn (ImportError $error): array => [
                    'row' => $error->row,
                    'field' => $error->field,
                    'message' => $error->message,
                    'value' => $error->value,
                ], $result->getErrors()),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($result): void {
            foreach ($result->getCreatedEntities() as $entity) {
                $entityManager->persist($entity);
            }

            // Updated entities returned by Doctrine are already managed.
            foreach ($result->getDeletedEntities() as $entity) {
                $entityManager->remove($entity);
            }
        });

        return $this->json([
            'created' => count($result->getCreatedEntities()),
            'updated' => count($result->getUpdatedEntities()),
            'deleted' => count($result->getDeletedEntities()),
        ]);
    }
}
```

Each data row is independent: malformed rows add structured `ImportError` objects and do not prevent later rows from being processed. The result contains candidate changes only; the bundle deliberately does not call `persist()`, `remove()` or `flush()`, so the application controls transactions and partial-import policy.

### Runtime operation permissions

The last three arguments of `import()` control which changes a specific call may produce:

```php
$result = $importer->import(
    $file,
    Product::class,
    ProductImportType::class,
    allowDelete: false,
    allowCreate: true,
    allowUpdate: true,
);
```

- `allowDelete` controls rows whose `deleted` value is true. The entity configuration must also have `allow_delete: true`; a runtime flag cannot add a column absent from the configured format.
- `allowCreate` controls rows for which no entity matches `unique_fields`.
- `allowUpdate` controls rows for which an existing entity matches `unique_fields` and deletion was not requested.

All three default to `true`, preserving existing calls. A forbidden operation adds an `ImportError` with the affected row, does not add an entity to the corresponding result list, and does not prevent later rows from being processed.

### Excel boolean values

Excel represents native logical values as `TRUE` and `FALSE` ([Microsoft documentation](https://support.microsoft.com/en-us/excel/functions/true-function)). This does not cause a case problem: textual boolean values are compared case-insensitively, so `true`, `TRUE`, `false` and `FALSE` work with the default configuration.

For XLSX files, native boolean cells are returned by PhpSpreadsheet as PHP booleans rather than strings. The importer normalizes them to the configured `bool_true`/`bool_false` tokens before validation. Consequently both native Excel booleans and textual uppercase values are supported. For custom tokens such as `yes`/`no`, native cells still work; textual cells must contain those configured tokens.

## Exporting

Pass a Doctrine ORM `Query`, getter methods in column order, a base filename, and a type:

```php
use HugoSEIGLE\SymfonyImportExportBundle\Services\Export\ExporterInterface;

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
use HugoSEIGLE\SymfonyImportExportBundle\Services\Import\ImporterInterface;
use HugoSEIGLE\SymfonyImportExportBundle\Services\Import\ImporterTemplateInterface;

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

## Upgrading from 1.x

Version 2 uses the canonical namespace `HugoSEIGLE\SymfonyImportExportBundle\...`. Replace imports from the former `SymfonyImportExportBundle\...` namespace, including the bundle class in `config/bundles.php` and injected service interfaces.

`ImporterInterface::import()` returns an `ImportResult`. The deprecated stateful methods from 1.x (`getErrors()`, `isValid()`, `getSummary()` and `getResult()`) have been removed; use only the returned result object.

The importer itself is stateless in 2.x. Configuration is injected directly by Symfony instead of being read through `ParameterBagInterface`. The unused `Spreadsheet` constructor argument on `Exporter` has also been removed. These constructor changes matter only when instantiating concrete services manually; normal applications should inject their interfaces.

## Compatibility

PHP 8.1 is the minimum required by the source and the lowest supported releases of Doctrine ORM 3, PhpSpreadsheet 2, PHPUnit 10 and Symfony 6.4. Symfony 7 and 8 are selected by Composer only on PHP versions satisfying their own constraints.

## Development

```bash
composer test
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/grumphp run
```
