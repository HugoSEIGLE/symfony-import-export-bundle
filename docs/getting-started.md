# Getting started in 5 minutes

This quick guide adds CSV import and export to an existing Symfony application. It assumes that Doctrine already manages a `Company` entity with `name`, `email`, and `active` properties and their getters and setters.

## 1. Install the bundle

```bash
composer require hugoseigle/symfony-import-export-bundle
```

Symfony Flex normally enables the bundle automatically. Otherwise, register `SymfonyImportExportBundle` as shown in the [installation guide](installation.md).

## 2. Configure the columns

```yaml
# config/packages/import_export.yaml
import_export:
    importers:
        App\Entity\Company:
            fields: [name, email, active]
            unique_fields: [email]
```

The field order defines the CSV column order. `email` identifies an existing company during an update.

## 3. Create the import form

The importer uses a Symfony form to map and validate every row:

```php
<?php
// src/Form/CompanyImportType.php

namespace App\Form;

use App\Entity\Company;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CompanyImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('email')
            ->add('active', CheckboxType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Company::class]);
    }
}
```

Add Symfony constraints to this form or to the entity when business validation is required.

## 4. Add an import endpoint

```php
<?php
// src/Controller/CompanyImportController.php

namespace App\Controller;

use App\Entity\Company;
use App\Form\CompanyImportType;
use Doctrine\ORM\EntityManagerInterface;
use HugoSEIGLE\SymfonyImportExportBundle\Services\Import\ImporterInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CompanyImportController
{
    #[Route('/companies/import', methods: ['POST'])]
    public function __invoke(
        Request $request,
        ImporterInterface $importer,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'A CSV or XLSX file is required.'], 400);
        }

        $result = $importer->import($file, Company::class, CompanyImportType::class);
        if (!$result->isValid()) {
            return new JsonResponse(['errors' => $result->getErrors()], 422);
        }

        $entityManager->wrapInTransaction(function () use ($entityManager, $result): void {
            foreach ($result->getCreatedEntities() as $company) {
                $entityManager->persist($company);
            }
        });

        return new JsonResponse([
            'created' => count($result->getCreatedEntities()),
            'updated' => count($result->getUpdatedEntities()),
        ]);
    }
}
```

Create a small file and send it to the endpoint:

```csv
name,email,active
Acme,hello@acme.test,true
Globex,hello@globex.test,false
```

```bash
curl -F file=@companies.csv http://localhost:8000/companies/import
```

The bundle returns candidate changes but never writes them automatically. The controller above persists new entities only after the complete file is valid; existing entities are already managed by Doctrine.

## 5. Add an export endpoint

```php
<?php
// src/Controller/CompanyExportController.php

namespace App\Controller;

use App\Entity\Company;
use Doctrine\Persistence\ManagerRegistry;
use HugoSEIGLE\SymfonyImportExportBundle\Services\Export\ExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class CompanyExportController
{
    #[Route('/companies/export', methods: ['GET'])]
    public function __invoke(ManagerRegistry $doctrine, ExporterInterface $exporter): StreamedResponse
    {
        $query = $doctrine->getRepository(Company::class)
            ->createQueryBuilder('company')
            ->getQuery();

        return $exporter->export(
            $query,
            ['getName', 'getEmail', 'isActive'],
            'companies',
            ExporterInterface::CSV,
        );
    }
}
```

Open `/companies/export` to download `companies.csv`. Replace `ExporterInterface::CSV` with `ExporterInterface::XLSX` for Excel.

## Next steps

- Review [import behavior](import.md), especially operation permissions and supported conversions.
- Configure formats, translated headers, or deletion in [customization](customization.md).
- Choose an atomic or partial-write strategy with the [validation guide](validation.md).
- Copy the complete, runnable files from the [Company example](../examples/README.md).
