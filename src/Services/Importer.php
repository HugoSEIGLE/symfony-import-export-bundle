<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Services;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Override;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function fclose;
use function fgetcsv;
use function fopen;

class Importer implements ImporterInterface
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly FormInterface $form,
        private readonly EntityManagerInterface $entityManager,
        private readonly array $errors = [],
    ) {
    }

    #[Override]
    public function import(UploadedFile $file, string $entityClass, string $formType): void
    {
        $form = $this->formFactory->create($formType, null, [
            'entity_class' => $entityClass,
        ]);

        if ('xlsx' === $file->getClientOriginalExtension()) {
            $fileData = $this->parseXlsxFile($file);
        } elseif ('csv' === $file->getClientOriginalExtension()) {
            $fileData = $this->parseCsvFile($file);
        } else {
            throw new InvalidArgumentException('Invalid file format');
        }

        foreach ($fileData as $row) {
            $form->submit($row);

            if ($form->isValid()) {
                $this->entityManager->persist($form->getData());
            } else {
                $this->errors[] = $form->getErrors(true);
            }
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function isValid(): bool
    {
        return [] === $this->errors;
    }

    private function parseXlsxFile(UploadedFile $file): array
    {
        $reader = new Xlsx();
        $spreadsheet = $reader->load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();

        $rows = [];
        foreach ($worksheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $cells = [];
            foreach ($cellIterator as $cell) {
                $cells[] = $cell->getValue();
            }

            $rows[] = $cells;
        }

        return $rows;
    }

    private function parseCsvFile(UploadedFile $file): array
    {
        $rows = [];
        $handle = fopen($file->getPathname(), 'r');

        while (false !== ($data = fgetcsv($handle))) {
            $rows[] = $data;
        }

        fclose($handle);

        return $rows;
    }
}
