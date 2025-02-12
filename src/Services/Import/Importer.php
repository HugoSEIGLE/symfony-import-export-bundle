<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services\Import;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_combine;
use function array_key_exists;
use function array_map;
use function array_shift;
use function class_exists;
use function explode;
use function fclose;
use function fgetcsv;
use function fopen;
use function get_class;
use function is_array;
use function is_object;
use function is_scalar;
use function is_string;
use function pathinfo;
use function sprintf;
use function strtolower;
use function strval;
use function trim;

use const PATHINFO_EXTENSION;

class Importer implements ImporterInterface
{
    /** @var array<string> */
    private array $errors = [];

    /** @var array{'created': array<int, object>, 'updated': array<int, object>, 'deleted': array<int, object>} */
    private array $summary = ['created' => [], 'updated' => [], 'deleted' => []];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FormFactoryInterface $formFactory,
        private readonly TranslatorInterface $translator,
        private readonly ParameterBagInterface $parameterBag,
        private readonly string $boolTrue = 'true',
    ) {
    }

    /**
     * @param class-string $entityClass
     * @param class-string $formType
     */
    public function import(UploadedFile $file, string $entityClass, string $formType): void
    {
        if (!class_exists($entityClass)) {
            throw new InvalidArgumentException($this->translator->trans('import_export.invalid_entity', [], 'messages'));
        }

        $importers = $this->parameterBag->get('import_export.importers');
        if (!is_array($importers) || !array_key_exists($entityClass, $importers)) {
            throw new InvalidArgumentException(sprintf('No import configuration found for entity %s.', $entityClass));
        }
        $config = (array) $importers[$entityClass];
        $allowDelete = $config['allow_delete'] ?? false;

        if ($allowDelete) {
            $config['fields'][] = 'deleted';
        }

        $uniqueFields = $config['unique_fields'] ?? [];

        $fileData = $this->parseFile($file);
        $header = array_shift($fileData);
        if (null === $header) {
            throw new InvalidArgumentException($this->translator->trans('import_export.empty_file', [], 'messages'));
        }

        $results = [];

        foreach ($fileData as $rowIndex => $row) {
            $rowData = $this->combineRowData($config['fields'], array_map('trim', $row));

            if ([] !== $this->errors) {
                continue;
            }

            $rowData = $this->formatRowData($rowData, $entityClass, $config);

            if ($this->isEmptyRow($rowData)) {
                continue;
            }

            $deleted = $rowData['deleted'] ?? false;
            unset($rowData['deleted']);

            $form = $this->formFactory->create($formType, null, ['data_class' => $entityClass, 'csrf_protection' => false]);
            $form->submit($rowData);

            if ($form->isValid()) {
                $entity = $form->getData();

                if (!is_object($entity)) {
                    $this->addError($rowIndex + 2, 'import_export.invalid_entity_data');
                    continue;
                }

                $existingEntity = $this->findExistingEntity(
                    $entityClass,
                    $uniqueFields,
                    array_map(static fn ($value) => is_scalar($value) || null === $value ? strval($value) : '', $rowData)
                );

                if (null !== $existingEntity) {
                    if ($this->boolTrue === $deleted) {
                        $this->deleteEntity($existingEntity);
                    } else {
                        $this->updateEntity($entity, $existingEntity, $config['fields']);
                    }
                } elseif ($this->boolTrue === $deleted) {
                    $this->addError($rowIndex + 2, 'import_export.deleted_entity_not_found');
                } else {
                    $this->persistEntity($entity);
                }

                $results[] = $entity;
            } else {
                $this->errors = $this->collectFormErrors($form, $rowIndex + 2);
            }
        }
    }

    /**
     * @param array<string, mixed> $rowData
     */
    private function isEmptyRow(array $rowData): bool
    {
        foreach ($rowData as $value) {
            if ('' !== $value && false !== $value && [] !== $value && null !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string> $fields
     * @param array<string> $row
     *
     * @return array<string, string>
     */
    private function combineRowData(array $fields, array $row): array
    {
        foreach ($fields as $index => $field) {
            if (!array_key_exists($index, $row)) {
                $this->errors[] = $this->translator->trans('import_export.missing_field', ['%field%' => $field], 'messages');
            }
        }

        if ([] !== $this->errors) {
            return [];
        }

        return array_combine($fields, $row);
    }

    /**
     * @param array<string, string> $rowData
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function formatRowData(array $rowData, string $entityClass, array $config): array
    {
        $metadata = $this->entityManager->getClassMetadata($entityClass);
        foreach ($rowData as $field => &$value) {
            $type = $metadata->getTypeOfField($field);

            switch ($type) {
                case 'boolean':
                    $value = strtolower(trim((string) $value)) === strtolower($this->boolTrue);
                    break;

                case 'datetime':
                    if (is_string($value)) {
                        $dateFormat = $this->parameterBag->get('import_export.date_format');
                        if (!is_string($dateFormat)) {
                            $this->errors[] = $this->translator->trans('import_export.invalid_date_format', [], 'messages');
                            continue 2;
                        }
                        $value = DateTime::createFromFormat($dateFormat, $value) ?: null;
                        if (!$value) {
                            $this->errors[] = $this->translator->trans('import_export.invalid_datetime', ['%field%' => $field], 'messages');
                        }
                    }
                    break;

                default:
                    if ($metadata->hasAssociation($field)) {
                        $value = $this->resolveEntityRelation($value, $metadata->isCollectionValuedAssociation($field));
                    }
                    break;
            }
        }

        return $rowData;
    }

    private function resolveEntityRelation(string $value, bool $isCollection): mixed
    {
        if (true === $isCollection) {
            if ('' === $value) {
                return [];
            }

            return array_map('trim', explode(',', $value));
        }

        if ('' === $value) {
            return null;
        }

        return $value;
    }

    private function persistEntity(object $entity): void
    {
        $this->summary['created'][] = $entity;
    }

    /**
     * @param array<string> $fields
     */
    private function updateEntity(object $entity, object $existingEntity, array $fields): void
    {
        $metadata = $this->entityManager->getClassMetadata(get_class($existingEntity));

        foreach ($fields as $field) {
            if ($metadata->hasField($field) || $metadata->hasAssociation($field)) {
                $value = $metadata->getFieldValue($entity, $field);
                $metadata->setFieldValue($existingEntity, $field, $value);
            }
        }

        $this->summary['updated'][] = $existingEntity;
    }

    private function deleteEntity(object $entity): void
    {
        $this->summary['deleted'][] = $entity;
    }

    /**
     * @param array<string, string> $uniqueFields
     * @param array<string, string> $rowData
     */
    private function findExistingEntity(string $entityClass, array $uniqueFields, array $rowData): ?object
    {
        $criteria = [];
        foreach ($uniqueFields as $field) {
            $criteria[$field] = $rowData[$field] ?? null;
        }

        /** @var class-string<object> $entityClass */
        return $this->entityManager->getRepository($entityClass)->findOneBy($criteria);
    }

    /**
     * @return array<array<string>>
     */
    private function parseFile(UploadedFile $file): array
    {
        $extension = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);

        return match ($extension) {
            ImporterInterface::CSV => $this->parseCsvFile($file),
            ImporterInterface::XLSX => $this->parseXlsxFile($file),
            default => throw new InvalidArgumentException(sprintf('Unsupported file type: %s', $extension)),
        };
    }

    /**
     * @return array<array<string>>
     */
    private function parseCsvFile(UploadedFile $file): array
    {
        $rows = [];
        if (($handle = fopen($file->getPathname(), 'r')) !== false) {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $rows[] = array_map(fn ($cell) => 'false' === $cell ? 'false' : trim((string) $cell), $data);
            }
            fclose($handle);
        }

        return $rows;
    }

    /**
     * @return array<array<string>>
     */
    private function parseXlsxFile(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();

        $rows = [];
        foreach ($worksheet->getRowIterator() as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $value = $cell->getValue();
                if (is_string($value)) {
                    $cells[] = trim($value);
                } elseif (is_scalar($value)) {
                    $cells[] = (string) $value;
                } else {
                    $cells[] = '';
                }
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    /**
     * @param FormInterface<object> $form
     *
     * @return array<string>
     */
    private function collectFormErrors(FormInterface $form, int $rowIndex): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            /** @var FormError $error */
            $fieldName = $error->getOrigin()?->getName() ?? '';
            $fieldTranslated = $this->translator->trans('import_export.' . strtolower($fieldName), [], 'messages');
            /** @var string $fieldTranslated */
            $errors[] = sprintf('Row %d: %s (%s)', $rowIndex, $error->getMessage(), $fieldTranslated);
        }

        return $errors;
    }

    private function addError(int $rowIndex, string $messageKey): void
    {
        $this->errors[] = sprintf('Row %d: %s', $rowIndex, $this->translator->trans($messageKey, [], 'messages'));
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function isValid(): bool
    {
        return [] === $this->errors;
    }

    public function getSummary(): array
    {
        return $this->summary;
    }
}
