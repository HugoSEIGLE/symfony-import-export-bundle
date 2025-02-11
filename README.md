![Packagist Version](https://img.shields.io/packagist/v/hugoseigle/symfony-import-export-bundle)
![Total Downloads](https://img.shields.io/packagist/dt/hugoseigle/symfony-import-export-bundle)

# 📦 Symfony ImportExportBundle

The SymfonyImportExportBundle simplifies data import, export, and template generation in Symfony applications. By leveraging Doctrine entities and Symfony forms, this bundle provides a seamless data management workflow.

## 🚀 Installation

### Install the bundle via Composer:

```bash
composer require hugoseigle/symfony-import-export-bundle
```

### Register the bundle in config/bundles.php:

```php

SymfonyImportExportBundle\SymfonyImportExportBundle::class => ['all' => true],
```

## ⚙️ Configuration

### Set up import_export.yaml in config/packages:

``` yaml

import_export:
  date_format: 'Y-m-d'
  bool_true: 'true'
  bool_false: 'false'
  importers:
    App\Entity\Product:
      fields:
        - name
        - description
        - price
        - active
        - createdAt
        - updatedAt
      allow_delete: true
      unique_fields: ['name']
```

### Configuration Options

``` yaml
    date_format: Format used for dates in import/export operations.
    bool_true / bool_false: Values for boolean true and false to ensure compatibility with different data sources.
    importers: Configure entity fields for import:
        fields: Define the fields to import.
        allow_delete: Enable or disable deletion of existing records.
        unique_fields: Specify unique fields for identifying existing entities.
```

## 📄 Usage

### ✨ Exporter

The Exporter allows exporting data from entities into CSV or XLSX files.
#### Basic Export Usage

```php

use SymfonyImportExportBundle\Services\Export\ExporterInterface;

// Inject the ExporterInterface
public function exportData(ExporterInterface $exporter): Response
{
    $query = $this->productRepository->yourQueryMethod();

    return $exporter->exportCsv($query, ['getName', 'getDescription', ...], 'fileName', ExporterInterface::XLSX); // or 'csv'
}
```

### ✨ Importer

The Importer allows importing data from CSV or XLSX files into entities, with validation handled by Symfony Forms.

#### Setting Up the Import Form

    Note: For boolean fields, set empty_data to false or true explicitly in the form type to ensure values are not interpreted as null.

```php

// src/Form/ProductType.php

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('description')
            ->add('price')
            ->add('active', CheckboxType::class, [
                'required' => false,
                'empty_data' => 'false', // Ensures boolean fields are handled correctly
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
```

#### Importing Data

```php

use SymfonyImportExportBundle\Services\Import\ImporterInterface;

// Inject the ImporterInterface
public function importData(Request $request, ImporterInterface $importer): Response
{
    $file = $request->files->get('import_file'); // Retrieve file from the form or request
    $importer->import($file, Product::class, ProductType::class);

    if ($importer->isValid()) {
        $summary = $importer->getSummary();

        foreach ($summary['created'] as $created) {
            $this->entityManager->persist($created);
        }

        foreach ($summary['updated'] as $updated) {
            $this->entityManager->persist($updated);
        }

        foreach ($summary['deleted'] as $deleted) {
            $deleted->delete();
        }

        $this->entityManager->flush();

        return new Response("Import successful! {$summary['inserted']} inserted, {$summary['updated']} updated.");
    } else {
        $errors = $importer->getErrors();
        return new Response("Import failed with errors: " . implode(', ', $errors));
    }
}
```

### ✨ Import Template Generator

The Import Template Generator creates CSV or XLSX templates with headers based on configured fields, allowing users to download pre-formatted templates.
Generating an Import Template

```php

use SymfonyImportExportBundle\Services\Import\ImporterTemplateInterface;

// Inject the ImporterTemplateInterface
public function generateImportTemplate(ImporterTemplateInterface $templateGenerator): Response
{
    return $templateGenerator->getImportTemplate(Product::class, ImporterInterface::XLSX); // or 'csv'
}
```

## 🔧 Advanced Usage

### Customizing Field Translations

To translate field names, add them to your translations/messages.yaml file:

```yaml

import_export:
  name: "Product Name"
  description: "Product Description"
  price: "Price"
  active: "Available"
```

### Error Handling and Custom Translations

Each validation error and import/export error can be translated. For example:

```yaml

import_export:
  missing_field: "Missing field: {{ field }}"
  invalid_boolean: "Invalid boolean value for: {{ field }}"
  invalid_datetime: "Invalid date format for: {{ field }}"
  invalid_headers: "The headers in the file do not match the expected format."
```

🛠 FAQ

Q: How do I customize date formats for imports?
A: Adjust the date_format option in import_export.yaml.

Q: How are boolean values handled during import?
A: Ensure the bool_true and bool_false values are configured in import_export.yaml to match data inputs. Set empty_data on boolean fields in the form.

Q: Can I specify unique fields for updating records?
A: Yes, add unique_fields in the configuration to identify existing records.
