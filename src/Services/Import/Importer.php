<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services\Import;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_combine;
use function array_map;
use function array_shift;
use function class_exists;
use function fclose;
use function fgetcsv;
use function fopen;
use function get_class_methods;
use function implode;
use function is_a;
use function is_array;
use function is_object;
use function is_scalar;
use function method_exists;
use function pathinfo;
use function sprintf;
use function str_replace;
use function str_starts_with;

use const PATHINFO_EXTENSION;

class Importer implements ImporterInterface
{
    /** @var array<string> */
    private array $errors = [];

    /** @var array<string, int> */
    private array $summary = ['inserted' => 0, 'updated' => 0, 'deleted' => 0];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FormFactoryInterface $formFactory,
        private readonly TranslatorInterface $translator,
        private readonly ParameterBagInterface $parameterBag,
        private readonly string $boolTrue = 'true',
    ) {
    }

    public function import(UploadedFile $file, string $entityClass, string $formType): void
    {
        if (!class_exists($entityClass)) {
            throw new InvalidArgumentException('Class must be an object.');
        }

        $importers = $this->parameterBag->get('symfony_import_export.importers');
        if (!is_array($importers) || !isset($importers[$entityClass])) {
            throw new InvalidArgumentException(sprintf('No import configuration found for entity %s.', $entityClass));
        }
        $config = (array) $importers[$entityClass];

        if (!$config) {
            throw new InvalidArgumentException(sprintf('No import configuration found for entity %s.', $entityClass));
        }

        $allowDelete = $config['allow_delete'] ?? false;
        $uniqueFields = $config['unique_fields'] ?? [];

        $fileData = $this->parseFile($file);

        $header = array_shift($fileData);
        if (null === $header) {
            throw new InvalidArgumentException('Empty file.');
        }

        foreach ($fileData as $row) {
            $rowData = array_combine($header, $row);

            if (!$rowData) {
                throw new InvalidArgumentException('Mismatch between header and row data.');
            }

            $form = $this->formFactory->create($formType, null, ['entity_class' => $entityClass]);
            $form->submit($rowData);

            if ($form->isValid()) {
                $entity = $form->getData();

                if (!is_object($entity)) {
                    throw new InvalidArgumentException('Form data must be an object.');
                }

                if ($existingEntity = $this->findExistingEntity($entityClass, $uniqueFields, $rowData)) {
                    $this->updateEntity($existingEntity, $entity);
                    ++$this->summary['updated'];
                } else {
                    $this->entityManager->persist($entity);
                    ++$this->summary['inserted'];
                }

                if ($allowDelete && isset($rowData['deleted']) && $this->boolTrue === $rowData[$this->translator->trans('import_export.deleted', [], 'messages') ? '' : 'deleted']) {
                    $this->entityManager->remove($entity);
                    ++$this->summary['deleted'];
                }
            } else {
                $this->errors[] = implode(', ', $this->collectFormErrors($form));
            }
        }

        $this->entityManager->flush();
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * @return array<string>
     */
    public function getSummary(): array
    {
        return array_map('strval', $this->summary);
    }

    /**
     * @return array<array<string>>
     */
    private function parseFile(UploadedFile $file): array
    {
        $extension = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);

        if ('csv' === $extension) {
            return $this->parseCsvFile($file);
        }

        if ('xlsx' === $extension) {
            return $this->parseXlsxFile($file);
        }

        throw new InvalidArgumentException(sprintf('Unsupported file type: %s', $extension));
    }

    /**
     * @return array<array<string>>
     */
    private function parseCsvFile(UploadedFile $file): array
    {
        $rows = [];
        if (($handle = fopen($file->getPathname(), 'r')) !== false) {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $rows[] = array_map(fn ($cell) => is_scalar($cell) ? (string) $cell : '', $data);
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
                $cells[] = is_scalar($value) ? (string) $value : '';
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    /**
     * @param array<string> $uniqueFields
     * @param array<string, string> $row
     */
    private function findExistingEntity(string $entityClass, array $uniqueFields, array $row): ?object
    {
        if (!is_a($entityClass, $entityClass, true)) {
            throw new InvalidArgumentException(sprintf('Invalid entity class: %s', $entityClass));
        }

        $criteria = [];
        foreach ($uniqueFields as $field) {
            if (!isset($row[$field])) {
                $this->errors[] = sprintf('Missing required unique field: %s', $field);

                return null;
            }
            $criteria[$field] = $row[$field];
        }

        return $this->entityManager->getRepository($entityClass)->findOneBy($criteria);
    }

    private function updateEntity(object $existingEntity, object $newData): void
    {
        foreach (get_class_methods($newData) as $method) {
            if (str_starts_with($method, 'get') && method_exists($existingEntity, str_replace('get', 'set', $method))) {
                $setter = str_replace('get', 'set', $method);
                $existingEntity->$setter($newData->$method());
            }
        }
    }

    /**
     * @return array<string>
     */
    private function collectFormErrors(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            /** @var \Symfony\Component\Form\FormError $error */
            $errors[] = $this->translator->trans($error->getMessage(), $error->getMessageParameters(), 'validators');
        }

        return $errors;
    }
}
