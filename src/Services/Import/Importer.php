<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Services\Import;

use BackedEnum;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use HugoSEIGLE\SymfonyImportExportBundle\Services\MethodToSnakeInterface;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

use function array_combine;
use function array_key_exists;
use function array_map;
use function class_exists;
use function count;
use function enum_exists;
use function explode;
use function fclose;
use function fgetcsv;
use function fopen;
use function get_class;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_object;
use function is_scalar;
use function is_string;
use function pathinfo;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

use const PATHINFO_EXTENSION;

class Importer implements ImporterInterface
{
    /** @param array<class-string, array<mixed, mixed>> $importers */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FormFactoryInterface $formFactory,
        private readonly TranslatorInterface $translator,
        private readonly array $importers,
        private readonly string $dateFormat = 'Y-m-d H:i:s',
        private readonly string $boolTrue = 'true',
        private readonly string $boolFalse = 'false',
        private readonly bool $validateHeaders = true,
        private readonly string $csvDelimiter = ',',
        private readonly string $csvEnclosure = '"',
        private readonly string $csvEscape = '\\',
        private readonly ?MethodToSnakeInterface $methodToSnake = null,
    ) {
    }

    /**
     * @param class-string $entityClass
     * @param class-string $formType
     * @param bool $allowDelete allow this call to produce deleted entities
     * @param bool $allowCreate allow this call to produce created entities
     * @param bool $allowUpdate allow this call to produce updated entities
     */
    public function import(
        UploadedFile $file,
        string $entityClass,
        string $formType,
        bool $allowDelete = true,
        bool $allowCreate = true,
        bool $allowUpdate = true,
    ): ImportResult {
        $result = new ImportResult();

        if (!class_exists($entityClass)) {
            throw new InvalidArgumentException($this->translate('import_export.invalid_entity'));
        }

        if (!array_key_exists($entityClass, $this->importers)) {
            throw new InvalidArgumentException(sprintf('No import configuration found for entity %s.', $entityClass));
        }

        $config = $this->importers[$entityClass];
        $fields = $this->getConfiguredFields($config);
        $uniqueFields = $this->getStringList($config['unique_fields'] ?? []);
        $validateHeaders = $config['validate_headers'] ?? $this->validateHeaders;
        if (!is_bool($validateHeaders)) {
            throw new InvalidArgumentException('The validate_headers option must be a boolean.');
        }
        $rows = $this->parseFile($file);
        $hasHeader = false;

        foreach ($rows as $fileRow => $row) {
            if (!$hasHeader) {
                $hasHeader = true;
                if ($validateHeaders && !$this->headersAreValid($row, $fields)) {
                    $this->addError($result, 1, null, sprintf(
                        'Invalid headers. Expected [%s], got [%s].',
                        implode(', ', $this->getExpectedHeaders($fields)),
                        implode(', ', $row),
                    ), $row);

                    return $result;
                }

                continue;
            }

            $rowNumber = $fileRow + 1;
            $errorCount = $result->getErrorCount();
            $rowData = $this->combineRowData($result, $fields, array_map(static fn (string $value): string => trim($value), $row), $rowNumber);
            if ($result->getErrorCount() > $errorCount) {
                continue;
            }

            if ($this->isEmptyRow($rowData)) {
                continue;
            }

            $rowData = $this->formatRowData($result, $rowData, $entityClass, $rowNumber);
            if ($result->getErrorCount() > $errorCount) {
                continue;
            }

            $deleted = $rowData['deleted'] ?? false;
            unset($rowData['deleted']);

            $form = $this->formFactory->create($formType, null, ['data_class' => $entityClass, 'csrf_protection' => false]);
            $form->submit($rowData);

            if (!$form->isValid()) {
                $this->collectFormErrors($result, $form, $rowNumber, $rowData);
                continue;
            }

            $entity = $form->getData();
            if (!is_object($entity)) {
                $this->addError($result, $rowNumber, null, $this->translate('import_export.invalid_entity_data'));
                continue;
            }

            $existingEntity = $this->findExistingEntity($entityClass, $uniqueFields, $rowData);
            if (null !== $existingEntity) {
                if (true === $deleted) {
                    if (!$allowDelete) {
                        $this->addOperationNotAllowedError($result, $rowNumber, 'delete');
                    } else {
                        $result->addDeletedEntity($existingEntity);
                    }
                } elseif (!$allowUpdate) {
                    $this->addOperationNotAllowedError($result, $rowNumber, 'update');
                } else {
                    $this->updateEntity($result, $entity, $existingEntity, $fields);
                }
            } elseif (true === $deleted) {
                if (!$allowDelete) {
                    $this->addOperationNotAllowedError($result, $rowNumber, 'delete');
                } else {
                    $this->addError($result, $rowNumber, 'deleted', $this->translate('import_export.deleted_entity_not_found'), $deleted);
                }
            } elseif (!$allowCreate) {
                $this->addOperationNotAllowedError($result, $rowNumber, 'create');
            } else {
                $result->addCreatedEntity($entity);
            }
        }

        if (!$hasHeader) {
            $this->addError($result, 1, null, $this->translate('import_export.empty_file'));
        }

        return $result;
    }

    /** @param array<mixed, mixed> $config
     * @return list<string>
     */
    private function getConfiguredFields(array $config): array
    {
        $fields = $this->getStringList($config['fields'] ?? []);
        if ($config['allow_delete'] ?? false) {
            $fields[] = 'deleted';
        }

        return $fields;
    }

    /** @return list<string> */
    private function getStringList(mixed $values): array
    {
        if (!is_array($values)) {
            throw new InvalidArgumentException('Expected a list of field names.');
        }

        $fields = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException('Field names must be strings.');
            }
            $fields[] = $value;
        }

        return $fields;
    }

    /** @param list<string> $actualHeaders
     * @param list<string> $fields
     */
    private function headersAreValid(array $actualHeaders, array $fields): bool
    {
        $actualHeaders = array_map(static function (string $header): string {
            $header = trim($header);

            return str_starts_with($header, "\xEF\xBB\xBF") ? substr($header, 3) : $header;
        }, $actualHeaders);

        return $actualHeaders === $fields || $actualHeaders === $this->getExpectedHeaders($fields);
    }

    /** @param list<string> $fields
     * @return list<string>
     */
    private function getExpectedHeaders(array $fields): array
    {
        return array_map(function (string $field): string {
            $key = 'import_export.' . ($this->methodToSnake?->convert($field) ?? strtolower($field));
            $translated = $this->translate($key);

            return '' === $translated ? $field : $translated;
        }, $fields);
    }

    /** @param array<string, mixed> $rowData */
    private function isEmptyRow(array $rowData): bool
    {
        foreach ($rowData as $value) {
            if ('' !== $value && false !== $value && [] !== $value && null !== $value) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $fields
     * @param list<string> $row
     *
     * @return array<string, string>
     */
    private function combineRowData(ImportResult $result, array $fields, array $row, int $rowNumber): array
    {
        if (count($fields) !== count($row)) {
            $this->addError($result, $rowNumber, null, sprintf('Expected %d columns, got %d.', count($fields), count($row)), $row);

            return [];
        }

        return array_combine($fields, $row);
    }

    /** @param array<string, string> $rowData
     * @return array<string, mixed>
     */
    private function formatRowData(ImportResult $result, array $rowData, string $entityClass, int $rowNumber): array
    {
        $metadata = $this->entityManager->getClassMetadata($entityClass);
        foreach ($rowData as $field => &$value) {
            $type = $metadata->hasField($field) ? $metadata->getTypeOfField($field) : null;

            if ('deleted' === $field || 'boolean' === $type) {
                $value = $this->parseBoolean($result, $value, $rowNumber, $field);
                continue;
            }

            if (in_array($type, ['date', 'datetime', 'date_immutable', 'datetime_immutable'], true)) {
                $value = $this->parseDate($result, $value, (string) $type, $rowNumber, $field);
                continue;
            }

            if ($metadata->hasField($field)) {
                $mapping = $metadata->getFieldMapping($field);
                if (null !== $mapping->enumType && enum_exists($mapping->enumType)) {
                    /** @var class-string<BackedEnum> $enumClass */
                    $enumClass = $mapping->enumType;

                    try {
                        $value = $enumClass::from($value);
                    } catch (Throwable) {
                        $this->addError($result, $rowNumber, $field, sprintf('Invalid enum value for field "%s".', $field), $value);
                    }
                    continue;
                }
            }

            if ($metadata->hasAssociation($field)) {
                $value = $this->resolveEntityRelation($value, $metadata->isCollectionValuedAssociation($field));
            }
        }
        unset($value);

        return $rowData;
    }

    private function parseBoolean(ImportResult $result, string $value, int $rowNumber, string $field): ?bool
    {
        $normalized = strtolower(trim($value));
        if ($normalized === strtolower(trim($this->boolTrue))) {
            return true;
        }
        if ($normalized === strtolower(trim($this->boolFalse))) {
            return false;
        }

        $this->addError($result, $rowNumber, $field, sprintf('Invalid boolean value. Expected "%s" or "%s".', $this->boolTrue, $this->boolFalse), $value);

        return null;
    }

    private function parseDate(ImportResult $result, string $value, string $type, int $rowNumber, string $field): ?DateTimeInterface
    {
        if ('' === $value) {
            return null;
        }

        $immutable = str_ends_with($type, '_immutable');
        $date = $immutable ? DateTimeImmutable::createFromFormat('!' . $this->dateFormat, $value) : DateTime::createFromFormat('!' . $this->dateFormat, $value);
        $parseErrors = DateTimeImmutable::getLastErrors();
        if (false === $date || (false !== $parseErrors && (0 < $parseErrors['warning_count'] || 0 < $parseErrors['error_count']))) {
            $this->addError($result, $rowNumber, $field, $this->translate('import_export.invalid_datetime', ['%field%' => $field]), $value);

            return null;
        }

        return $date;
    }

    /** @return string|list<string>|null */
    private function resolveEntityRelation(string $value, bool $isCollection): string|array|null
    {
        if ($isCollection) {
            return '' === $value ? [] : array_map('trim', explode(',', $value));
        }

        return '' === $value ? null : $value;
    }

    /** @param list<string> $fields */
    private function updateEntity(ImportResult $result, object $entity, object $existingEntity, array $fields): void
    {
        $metadata = $this->entityManager->getClassMetadata(get_class($existingEntity));
        foreach ($fields as $field) {
            if ($metadata->hasField($field) || $metadata->hasAssociation($field)) {
                $metadata->setFieldValue($existingEntity, $field, $metadata->getFieldValue($entity, $field));
            }
        }

        $result->addUpdatedEntity($existingEntity);
    }

    /** @param list<string> $uniqueFields
     * @param array<string, mixed> $rowData
     */
    private function findExistingEntity(string $entityClass, array $uniqueFields, array $rowData): ?object
    {
        if ([] === $uniqueFields) {
            return null;
        }

        $criteria = [];
        foreach ($uniqueFields as $field) {
            $criteria[$field] = $rowData[$field] ?? null;
        }

        /** @var class-string<object> $entityClass */
        return $this->entityManager->getRepository($entityClass)->findOneBy($criteria);
    }

    /** @return Generator<int, list<string>> */
    private function parseFile(UploadedFile $file): Generator
    {
        $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

        return match ($extension) {
            self::CSV => $this->parseCsvFile($file),
            self::XLSX => $this->parseXlsxFile($file),
            default => throw new InvalidArgumentException(sprintf('Unsupported file type: %s', $extension)),
        };
    }

    /** @return Generator<int, list<string>> */
    private function parseCsvFile(UploadedFile $file): Generator
    {
        $handle = fopen($file->getPathname(), 'r');
        if (false === $handle) {
            throw new InvalidArgumentException('Unable to open the CSV file.');
        }

        try {
            $row = 0;
            while (false !== ($data = fgetcsv($handle, null, $this->csvDelimiter, $this->csvEnclosure, $this->csvEscape))) {
                yield $row++ => array_map(static fn (mixed $cell): string => trim((string) $cell), $data);
            }
        } finally {
            fclose($handle);
        }
    }

    /** @return Generator<int, list<string>> */
    private function parseXlsxFile(UploadedFile $file): Generator
    {
        $reader = IOFactory::createReaderForFile($file->getPathname());
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $spreadsheet = $reader->load($file->getPathname());

        try {
            foreach ($spreadsheet->getActiveSheet()->getRowIterator() as $index => $row) {
                $cells = [];
                foreach ($row->getCellIterator() as $cell) {
                    $value = $cell->getValue();
                    $cells[] = match (true) {
                        true === $value => $this->boolTrue,
                        false === $value => $this->boolFalse,
                        is_scalar($value) => trim((string) $value),
                        default => '',
                    };
                }
                yield $index - 1 => $cells;
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /** @param FormInterface<object> $form
     * @param array<string, mixed> $rowData
     */
    private function collectFormErrors(ImportResult $result, FormInterface $form, int $rowNumber, array $rowData): void
    {
        foreach ($form->getErrors(true) as $error) {
            /** @var FormError $error */
            $field = $error->getOrigin()?->getName();
            $this->addError($result, $rowNumber, $field, $error->getMessage(), null === $field ? null : ($rowData[$field] ?? null));
        }
    }

    private function addError(ImportResult $result, int $row, ?string $field, string $message, mixed $value = null): void
    {
        $result->addError(new ImportError($row, $field, $message, $value));
    }

    private function addOperationNotAllowedError(ImportResult $result, int $row, string $operation): void
    {
        $key = 'import_export.operation_not_allowed';
        $message = $this->translate($key, ['%operation%' => $operation]);
        if ($key === $message) {
            $message = sprintf('Operation "%s" is not allowed.', $operation);
        }

        $this->addError(
            $result,
            $row,
            null,
            $message,
            $operation,
        );
    }

    /** @param array<string, mixed> $parameters */
    private function translate(string $key, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters, 'messages');
    }
}
