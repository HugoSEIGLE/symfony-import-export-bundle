<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Tests\Services\Import;

use Doctrine\ORM\EntityManagerInterface;
use function fclose;
use function fopen;
use function fputcsv;
use function sys_get_temp_dir;
use function unlink;
use Override;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use SymfonyImportExportBundle\Services\Import\Importer;
use SymfonyImportExportBundle\Services\Import\ImporterInterface;

class ImporterTest extends TestCase implements ImporterTestInterface
{
    private ImporterInterface $importer;
    private EntityManagerInterface $entityManager;
    private FormFactoryInterface $formFactory;
    private FormInterface $form;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->form = $this->createMock(FormInterface::class);

        $this->importer = new Importer(
            $this->formFactory,
            $this->form,
            $this->entityManager
        );
    }

    #[Override]
    public function testImportXlsxFile(): void
    {
        $testFilePath = $this->createTestXlsxFile();

        $file = new UploadedFile(
            $testFilePath,
            'test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->formFactory->expects($this->any())
            ->method('create')
            ->willReturn($this->form);

        $this->form->expects($this->any())
            ->method('isValid')
            ->willReturn(true);

        $entityMock = $this->getMockBuilder('App\Entity\TestEntity')
            ->disableOriginalConstructor()
            ->getMock();

        $this->form->expects($this->any())
        ->method('getData')
        ->willReturn($entityMock);

        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist')
            ->with($this->isInstanceOf('App\Entity\TestEntity'));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->importer->import($file, 'App\Entity\TestEntity', 'App\Form\TestFormType');

        $this->assertTrue($this->importer->isValid());

        unlink($testFilePath);
    }

    #[Override]
    public function testImportCsvFile(): void
    {
        $testFilePath = $this->createTestCsvFile();

        $file = new UploadedFile(
            $testFilePath,
            'test.csv',
            'text/csv',
            null,
            true
        );

        $this->formFactory->expects($this->any())
            ->method('create')
            ->willReturn($this->form);

        $this->form->expects($this->any())
            ->method('isValid')
            ->willReturn(true);

        $entityMock = $this->getMockBuilder('App\Entity\TestEntity')
            ->disableOriginalConstructor()
            ->getMock();

        $this->form->expects($this->any())
            ->method('getData')
            ->willReturn($entityMock);

        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist')
            ->with($this->isInstanceOf('App\Entity\TestEntity'));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->importer->import($file, 'App\Entity\TestEntity', 'App\Form\TestFormType');

        $this->assertTrue($this->importer->isValid());

        unlink($testFilePath);
    }

    private function createTestXlsxFile(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['name', 'email'],
            ['John Doe', 'john@example.com'],
        ]);

        $testFilePath = sys_get_temp_dir() . '/test.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($testFilePath);

        return $testFilePath;
    }

    private function createTestCsvFile(): string
    {
        $testFilePath = sys_get_temp_dir() . '/test.csv';
        $file = fopen($testFilePath, 'w');
        fputcsv($file, ['name', 'email']);
        fputcsv($file, ['John Doe', 'john@example.com']);
        fclose($file);

        return $testFilePath;
    }
}
