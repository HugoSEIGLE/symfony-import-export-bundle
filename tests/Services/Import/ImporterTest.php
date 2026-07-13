<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Tests\Services\Import;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyImportExportBundle\Services\Import\ImportResult;
use SymfonyImportExportBundle\Services\Import\Importer;
use SymfonyImportExportBundle\Tests\Entity\TestEntity;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

enum TestStatus: string
{
    case Active = 'active';
}

final class ImporterTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private FormFactoryInterface&MockObject $formFactory;
    private ParameterBagInterface&MockObject $parameterBag;
    private ClassMetadata $metadata;

    /** @var array<string, mixed> */
    private array $config = [];

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->parameterBag->method('get')->willReturnCallback(function (string $name): mixed {
            return match ($name) {
                'import_export.importers' => [TestEntity::class => $this->config],
                'import_export.date_format' => 'Y-m-d',
                default => null,
            };
        });

        $this->metadata = new ClassMetadata(TestEntity::class);
        $this->entityManager->method('getClassMetadata')->willReturnCallback(fn (): ClassMetadata => $this->metadata);
    }

    public function testUniqueFieldsEmptyNeverQueriesRepository(): void
    {
        $this->configure(['name']);
        $this->entityManager->expects(self::never())->method('getRepository');
        $this->formFactory->method('create')->willReturn($this->validForm());

        $result = $this->importer()->import($this->file("name\nAlice\n"), TestEntity::class, 'App\Form\TestType');

        self::assertCount(1, $result->getCreatedEntities());
    }

    public function testInvalidHeadersStopImportWithExplicitError(): void
    {
        $this->configure(['name', 'email']);
        $this->formFactory->expects(self::never())->method('create');

        $result = $this->importer()->import($this->file("email,name\na@example.com,Alice\n"), TestEntity::class, 'form');

        self::assertFalse($result->isValid());
        self::assertSame(1, $result->getErrors()[0]->row);
        self::assertStringContainsString('Invalid headers', $result->getErrors()[0]->message);
    }

    public function testInvalidBooleanIsRejected(): void
    {
        $this->metadata->mapField(['fieldName' => 'active', 'type' => 'boolean']);
        $this->configure(['active']);

        $result = $this->importer()->import($this->file("active\nperhaps\n"), TestEntity::class, 'form');

        self::assertSame('active', $result->getErrors()[0]->field);
        self::assertSame('perhaps', $result->getErrors()[0]->value);
        self::assertCount(0, $result->getCreatedEntities());
    }

    public function testAllErrorsAreAccumulatedAndLaterRowsContinue(): void
    {
        $this->metadata->mapField(['fieldName' => 'active', 'type' => 'boolean']);
        $this->configure(['active']);
        $this->formFactory->expects(self::once())->method('create')->willReturn($this->validForm());

        $result = $this->importer()->import($this->file("active\ninvalid\nalso-invalid\ntrue\n"), TestEntity::class, 'form');

        self::assertCount(2, $result->getErrors());
        self::assertSame([2, 3], [$result->getErrors()[0]->row, $result->getErrors()[1]->row]);
        self::assertCount(1, $result->getCreatedEntities());
    }

    public function testStateIsCompletelyResetBetweenImports(): void
    {
        $this->metadata->mapField(['fieldName' => 'active', 'type' => 'boolean']);
        $this->configure(['active']);
        $importer = $this->importer();
        $importer->import($this->file("active\ninvalid\n"), TestEntity::class, 'form');
        self::assertFalse($importer->isValid());

        $this->formFactory->method('create')->willReturn($this->validForm());
        $secondResult = $importer->import($this->file("active\nfalse\n"), TestEntity::class, 'form');

        self::assertTrue($secondResult->isValid());
        self::assertSame([], $importer->getErrors());
        self::assertCount(1, $importer->getSummary()['created']);
    }

    public function testUppercaseCsvExtensionIsAccepted(): void
    {
        $this->configure(['name']);
        $this->formFactory->method('create')->willReturn($this->validForm());

        $result = $this->importer()->import($this->file("name\nAlice\n", 'IMPORT.CSV'), TestEntity::class, 'form');

        self::assertTrue($result->isValid());
    }

    public function testMixedCaseXlsxExtensionIsAccepted(): void
    {
        $this->configure(['name']);
        $this->formFactory->method('create')->willReturn($this->validForm());

        $result = $this->importer()->import($this->xlsxFile([['name'], ['Alice']], 'IMPORT.Xlsx'), TestEntity::class, 'form');

        self::assertTrue($result->isValid());
        self::assertCount(1, $result->getCreatedEntities());
    }

    public function testXlsxNativeAndUppercaseBooleansAreAccepted(): void
    {
        $this->metadata->mapField(['fieldName' => 'active', 'type' => 'boolean']);
        $this->configure(['active']);
        $this->formFactory->method('create')->willReturn($this->validForm());

        $result = $this->importer()->import(
            $this->xlsxFile([['active'], [true], [false], ['TRUE'], ['FALSE']], 'booleans.xlsx'),
            TestEntity::class,
            'form',
        );

        self::assertTrue($result->isValid());
        self::assertCount(4, $result->getCreatedEntities());
    }

    public function testEmptyFileReturnsAnError(): void
    {
        $this->configure(['name']);

        $result = $this->importer()->import($this->file(''), TestEntity::class, 'form');

        self::assertFalse($result->isValid());
        self::assertSame(1, $result->getErrors()[0]->row);
    }

    public function testHeaderOnlyFileIsAValidEmptyImport(): void
    {
        $this->configure(['name']);

        $result = $this->importer()->import($this->file("name\n"), TestEntity::class, 'form');

        self::assertTrue($result->isValid());
        self::assertSame([], $result->getCreatedEntities());
    }

    public function testInvalidDateIsRejectedStrictly(): void
    {
        $this->metadata->mapField(['fieldName' => 'createdAt', 'type' => 'datetime_immutable']);
        $this->configure(['createdAt']);

        $result = $this->importer()->import($this->file("createdAt\n2024-02-31\n"), TestEntity::class, 'form');

        self::assertSame('createdAt', $result->getErrors()[0]->field);
        self::assertSame('2024-02-31', $result->getErrors()[0]->value);
    }

    public function testImmutableDateIsPassedToForm(): void
    {
        $this->metadata->mapField(['fieldName' => 'createdAt', 'type' => 'date_immutable']);
        $this->configure(['createdAt']);
        $form = $this->validForm();
        $form->expects(self::once())->method('submit')->with(self::callback(
            static fn (array $data): bool => $data['createdAt'] instanceof DateTimeImmutable,
        ));
        $this->formFactory->method('create')->willReturn($form);

        $result = $this->importer()->import($this->file("createdAt\n2024-02-29\n"), TestEntity::class, 'form');

        self::assertTrue($result->isValid());
    }

    public function testDoctrineRelationsAndCollectionsArePreparedForTheForm(): void
    {
        $this->metadata->mapManyToOne(['fieldName' => 'parent', 'targetEntity' => TestEntity::class]);
        $this->metadata->mapManyToMany(['fieldName' => 'relations', 'targetEntity' => TestEntity::class]);
        $this->configure(['parent', 'relations']);
        $form = $this->validForm();
        $form->expects(self::once())->method('submit')->with([
            'parent' => '42',
            'relations' => ['1', '2', '3'],
        ]);
        $this->formFactory->method('create')->willReturn($form);

        $result = $this->importer()->import($this->file("parent,relations\n42,\"1, 2, 3\"\n"), TestEntity::class, 'form');

        self::assertTrue($result->isValid());
    }

    public function testBackedEnumIsConvertedBeforeFormSubmission(): void
    {
        $this->metadata->mapField(['fieldName' => 'status', 'type' => 'string', 'enumType' => TestStatus::class]);
        $this->configure(['status']);
        $form = $this->validForm();
        $form->expects(self::once())->method('submit')->with(['status' => TestStatus::Active]);
        $this->formFactory->method('create')->willReturn($form);

        $result = $this->importer()->import($this->file("status\nactive\n"), TestEntity::class, 'form');

        self::assertTrue($result->isValid());
    }

    public function testCreateCanBeDisabledPerImport(): void
    {
        $this->configure(['name']);
        $this->formFactory->method('create')->willReturn($this->validForm());

        $result = $this->importer()->import(
            $this->file("name\nAlice\n"),
            TestEntity::class,
            'form',
            allowCreate: false,
        );

        self::assertSame('create', $result->getErrors()[0]->value);
        self::assertSame('Operation "create" is not allowed.', $result->getErrors()[0]->message);
        self::assertSame([], $result->getCreatedEntities());
    }

    public function testUpdateCanBeDisabledPerImport(): void
    {
        $this->metadata->mapField(['fieldName' => 'name', 'type' => 'string']);
        $this->configure(['name'], uniqueFields: ['name']);
        $this->repositoryReturning(new TestEntity());
        $this->formFactory->method('create')->willReturn($this->validForm());

        $result = $this->importer()->import(
            $this->file("name\nAlice\n"),
            TestEntity::class,
            'form',
            allowUpdate: false,
        );

        self::assertSame('update', $result->getErrors()[0]->value);
        self::assertSame([], $result->getUpdatedEntities());
    }

    public function testDeleteCanBeDisabledPerImport(): void
    {
        $this->configure(['name'], allowDelete: true, uniqueFields: ['name']);
        $this->repositoryReturning(new TestEntity());
        $this->formFactory->method('create')->willReturn($this->validForm());

        $result = $this->importer()->import(
            $this->file("name,deleted\nAlice,TRUE\n"),
            TestEntity::class,
            'form',
            allowDelete: false,
        );

        self::assertSame('delete', $result->getErrors()[0]->value);
        self::assertSame([], $result->getDeletedEntities());
    }

    public function testUpdateRemainsAllowedByDefault(): void
    {
        $this->metadata->mapField(['fieldName' => 'name', 'type' => 'string']);
        $this->metadata->wakeupReflection(new RuntimeReflectionService());
        $this->configure(['name'], uniqueFields: ['name']);
        $this->repositoryReturning(new TestEntity());
        $this->formFactory->method('create')->willReturn($this->validForm());

        $result = $this->importer()->import($this->file("name\nAlice\n"), TestEntity::class, 'form');

        self::assertTrue($result->isValid());
        self::assertCount(1, $result->getUpdatedEntities());
    }

    public function testDeleteRemainsAllowedByDefaultWhenConfigured(): void
    {
        $this->configure(['name'], allowDelete: true, uniqueFields: ['name']);
        $this->repositoryReturning(new TestEntity());
        $this->formFactory->method('create')->willReturn($this->validForm());

        $result = $this->importer()->import(
            $this->file("name,deleted\nAlice,TRUE\n"),
            TestEntity::class,
            'form',
        );

        self::assertTrue($result->isValid());
        self::assertCount(1, $result->getDeletedEntities());
    }

    /** @param list<string> $fields */
    private function configure(array $fields, bool $allowDelete = false, array $uniqueFields = []): void
    {
        $this->config = [
            'fields' => $fields,
            'allow_delete' => $allowDelete,
            'unique_fields' => $uniqueFields,
        ];
    }

    private function repositoryReturning(object $entity): void
    {
        $repository = $this->getMockBuilder(EntityRepository::class)->disableOriginalConstructor()->getMock();
        $repository->method('findOneBy')->willReturn($entity);
        $this->entityManager->method('getRepository')->willReturn($repository);
    }

    private function importer(): Importer
    {
        return new Importer($this->entityManager, $this->formFactory, $this->translator(), $this->parameterBag);
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }

    /** @return FormInterface<object>&MockObject */
    private function validForm(): FormInterface&MockObject
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(new TestEntity());

        return $form;
    }

    private function file(string $content, string $originalName = 'import.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import-test-');
        self::assertNotFalse($path);
        file_put_contents($path, $content);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $originalName, null, null, true);
    }

    /** @param list<list<string>> $rows */
    private function xlsxFile(array $rows, string $originalName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import-xlsx-test-');
        self::assertNotFalse($path);
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows);
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                if (is_bool($value)) {
                    $coordinate = Coordinate::stringFromColumnIndex($columnIndex + 1) . ($rowIndex + 1);
                    $spreadsheet->getActiveSheet()->setCellValueExplicit($coordinate, $value, DataType::TYPE_BOOL);
                }
            }
        }
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $originalName, null, null, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
