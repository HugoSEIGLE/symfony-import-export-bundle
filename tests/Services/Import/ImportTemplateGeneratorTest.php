<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Tests\Services\Import;

use function file_get_contents;
use function file_put_contents;
use function ob_get_clean;
use function ob_start;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyImportExportBundle\Services\Import\ImporterInterface;
use SymfonyImportExportBundle\Services\Import\ImporterTemplate;
use SymfonyImportExportBundle\Services\Import\ImporterTemplateInterface;
use SymfonyImportExportBundle\Services\MethodToSnake;
use SymfonyImportExportBundle\Services\MethodToSnakeInterface;

class ImportTemplateGeneratorTest extends TestCase implements ImportTemplateGeneratorTestInterface
{
    private ImporterTemplateInterface $importer;
    private ParameterBagInterface $parameterBag;
    private TranslatorInterface $translator;
    private readonly MethodToSnakeInterface $methodToSnake;

    protected function setUp(): void
    {
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->parameterBag->method('get')->with('import_export.importers')->willReturn([
            'SymfonyImportExportBundle\Tests\Entity\TestEntity' => [
                'fields' => ['id', 'name', 'email', 'created_at'],
            ],
        ]);

        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->methodToSnake = new MethodToSnake();

        $this->importer = new ImporterTemplate($this->parameterBag, $this->translator, $this->methodToSnake);
    }

    public function testGetImportTemplateXlsx(): void
    {
        $response = $this->importer->getImportTemplate('SymfonyImportExportBundle\Tests\Entity\TestEntity', ImporterInterface::XLSX);
        $this->assertInstanceOf(StreamedResponse::class, $response);

        $tempFilePath = tempnam(sys_get_temp_dir(), 'import_template') . '.xlsx';
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        file_put_contents($tempFilePath, $content);

        $this->assertFileExists($tempFilePath);

        $spreadsheet = IOFactory::load($tempFilePath);
        $sheet = $spreadsheet->getActiveSheet();

        $expectedHeaders = ['id', 'name', 'email', 'created_at'];
        foreach ($expectedHeaders as $col => $header) {
            $cell = Coordinate::stringFromColumnIndex($col + 1) . '1';
            $this->assertEquals($header, $sheet->getCell($cell)->getValue());
        }

        unlink($tempFilePath);
    }

    public function testGetImportTemplateCsv(): void
    {
        $response = $this->importer->getImportTemplate('SymfonyImportExportBundle\Tests\Entity\TestEntity', ImporterInterface::CSV);
        $this->assertInstanceOf(StreamedResponse::class, $response);

        $tempFilePath = tempnam(sys_get_temp_dir(), 'import_template') . '.csv';
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        file_put_contents($tempFilePath, $content);

        $this->assertFileExists($tempFilePath);

        $csv = file_get_contents($tempFilePath);
        $expectedHeaders = "id,name,email,created_at\n";

        $this->assertStringStartsWith($expectedHeaders, $csv);

        unlink($tempFilePath);
    }
}
