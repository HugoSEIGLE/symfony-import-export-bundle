<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Tests\Services\Import;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyImportExportBundle\Services\Import\Importer;
use SymfonyImportExportBundle\Services\Import\ImporterInterface;

use SymfonyImportExportBundle\Tests\Entity\TestEntity;
use SymfonyImportExportBundle\Tests\Repository\TestRepository;

use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function file_put_contents;

class ImporterTest extends TestCase
{
    private ImporterInterface $importer;
    private EntityManagerInterface $entityManager;
    private FormFactoryInterface $formFactory;
    private TranslatorInterface $translator;
    private ParameterBagInterface $parameterBag;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);

        $this->parameterBag->method('get')->with('symfony_import_export.importers')->willReturn([
            'SymfonyImportExportBundle\Tests\Entity\TestEntity' => [
                'fields' => ['id', 'name', 'email', 'created_at'],
                'allow_delete' => true,
                'unique_fields' => ['id']
            ],
        ]);

        $this->importer = new Importer(
            $this->entityManager,
            $this->formFactory,
            $this->translator,
            $this->parameterBag
        );
    }

    public function testImportValidData(): void
    {
        $file = $this->createTestFile("id,name,email,created_at\n1,John Doe,john@example.com,2023-01-01\n");

        $formMock = $this->createMock(FormInterface::class);
        $formMock->method('isValid')->willReturn(true);
        $formMock->method('getData')->willReturn(new \stdClass());

        $this->formFactory->method('create')->willReturn($formMock);

        $this->importer->import($file, 'SymfonyImportExportBundle\Tests\Entity\TestEntity', 'App\Form\TestFormType');

        $summary = $this->importer->getSummary();
        $this->assertEquals(1, $summary['inserted'], "Expected one item to be inserted.");
    }

    public function testImportInvalidData(): void
    {
        $file = $this->createTestFile("id,name,email,created_at\n,John Doe,john@example.com,2023-01-01\n");

        $formMock = $this->createMock(FormInterface::class);
        $formMock->method('isValid')->willReturn(false);

        $this->formFactory->method('create')->willReturn($formMock);

        $this->importer->import($file, 'SymfonyImportExportBundle\Tests\Entity\TestEntity', 'App\Form\TestFormType');

        $this->assertFalse($this->importer->isValid());
        $this->assertNotEmpty($this->importer->getErrors());
    }

    public function testAllowDeleteWithDeletedField(): void
    {
        $file = $this->createTestFile("id,name,email,created_at,deleted\n1,John Doe,john@example.com,2023-01-01,true\n");

        $formMock = $this->createMock(FormInterface::class);
        $formMock->method('isValid')->willReturn(true);
        $formMock->method('getData')->willReturn(new \stdClass());

        $this->formFactory->method('create')->willReturn($formMock);

        $this->importer->import($file, 'SymfonyImportExportBundle\Tests\Entity\TestEntity', 'App\Form\TestFormType');

        $summary = $this->importer->getSummary();
        $this->assertEquals(1, $summary['deleted'], "Expected one item to be deleted.");
    }

    public function testUpdateExistingEntity(): void
    {
        $file = $this->createTestFile("id,name,email,created_at,deleted\n1,John Updated,john_updated@example.com,2023-01-01,false\n");

        // Mock de l'entité existante pour simuler la mise à jour
        $existingEntity = new \stdClass();
        $existingEntity->id = 1;

        // Crée un mock pour EntityRepository
        $repositoryMock = $this->createMock(TestRepository::class);
        $repositoryMock->method('findOneBy')->willReturn($existingEntity);

        // Configure le EntityManager pour retourner le mock du repository
        $this->entityManager->method('getRepository')->willReturn($repositoryMock);

        // Mock du formulaire pour valider et retourner l’entité modifiée
        $formMock = $this->createMock(FormInterface::class);
        $formMock->method('isValid')->willReturn(true);
        $formMock->method('getData')->willReturn($existingEntity);  // Utilise l'entité existante

        $this->formFactory->method('create')->willReturn($formMock);

        // Exécuter l'import
        $this->importer->import($file, 'SymfonyImportExportBundle\Tests\Entity\TestEntity', 'App\Form\TestFormType');

        // Vérification du récapitulatif
        $summary = $this->importer->getSummary();
        $this->assertEquals(1, $summary['updated'], "Expected one item to be updated.");
    }

    private function createTestFile(string $content): UploadedFile
    {
        $filePath = tempnam(sys_get_temp_dir(), 'test_import_file');
        file_put_contents($filePath, $content);

        return new UploadedFile($filePath, 'test_import.csv', 'text/csv', null, true);
    }

    protected function tearDown(): void
    {
        unlink($this->createTestFile('')->getRealPath());
    }
}
