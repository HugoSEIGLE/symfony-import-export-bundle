<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Tests\Services;

use DateTime;
use Doctrine\ORM\Query;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyImportExportBundle\Services\Exporter;
use SymfonyImportExportBundle\Services\ExporterInterface;
use SymfonyImportExportBundle\Services\MethodToSnake;
use SymfonyImportExportBundle\Services\MethodToSnakeInterface;
use SymfonyImportExportBundle\Tests\Entity\TestEntity;

use function file_get_contents;
use function file_put_contents;
use function ob_get_clean;
use function ob_start;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

class ExporterTest extends TestCase
{
    private ExporterInterface $exporter;
    private MethodToSnakeInterface $methodToSnake;

    protected function setUp(): void
    {
        $spreadsheet = new Spreadsheet();

        $translatorMock = $this->createMock(TranslatorInterface::class);
        $translatorMock->method('trans')->willReturnArgument(0);

        /** @var TranslatorInterface $translatorMock */

        $this->methodToSnake = new MethodToSnake();

        $this->exporter = new Exporter($spreadsheet, $translatorMock, $this->methodToSnake);
    }

    public function testExportToXlsxGeneratesCorrectResponse(): void
    {
        $query = $this->createQuery();
        $methods = $this->getMethods();
        $response = $this->exporter->exportXlsx($query, $methods, 'export');

        $this->assertInstanceOf(StreamedResponse::class, $response);

        $tempFilePath = tempnam(sys_get_temp_dir(), 'export') . '.xlsx';

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        file_put_contents($tempFilePath, $content);

        $this->assertFileExists($tempFilePath);

        $spreadsheet = IOFactory::load($tempFilePath);
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($methods as $col => $method) {
            $expectedHeader = 'import_export.' . $this->methodToSnake->convert($method);
            $this->assertEquals($expectedHeader, $sheet->getCellByColumnAndRow($col + 1, 1)->getValue());
        }

        $this->assertEquals(1, $sheet->getCell('A2')->getValue());
        $this->assertEquals('John Doe', $sheet->getCell('B2')->getValue());
        $this->assertEquals('john@example.com', $sheet->getCell('C2')->getValue());
        $this->assertEquals('2023-01-01 10:00:00', $sheet->getCell('D2')->getValue());
        $this->assertEquals('2023-01-02 12:00:00', $sheet->getCell('E2')->getValue());
        $this->assertEquals(99.99, $sheet->getCell('F2')->getValue());
        $this->assertEquals('tag1, tag2', $sheet->getCell('G2')->getValue());
        $this->assertEquals('true', $sheet->getCell('H2')->getValue());

        unlink($tempFilePath);
    }

    public function testExportToCsvGeneratesCorrectResponse(): void
    {
        $query = $this->createQuery();
        $methods = $this->getMethods();
        $response = $this->exporter->exportCsv($query, $methods, 'export');

        $this->assertInstanceOf(StreamedResponse::class, $response);

        $tempFilePath = tempnam(sys_get_temp_dir(), 'export') . '.csv';

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        file_put_contents($tempFilePath, $content);

        $this->assertFileExists($tempFilePath);

        $csv = file_get_contents($tempFilePath);

        $expectedHeaders = implode(',', array_map(fn($method) => 'import_export.' . $this->methodToSnake->convert($method), $methods));

        $expectedCsv = $expectedHeaders . "\n"
        . "1,\"John Doe\",john@example.com,\"2023-01-01 10:00:00\",\"2023-01-02 12:00:00\",99.99,\"tag1, tag2\",true\n";



        $this->assertEquals($expectedCsv, $csv);

        unlink($tempFilePath);
    }

    private function getMethods(): array
    {
        return ['getId', 'getName', 'getEmail', 'getCreatedAt', 'getUpdatedAt', 'getPrice', 'getTags', 'isActive'];
    }

    private function createQuery(): Query
    {
        $queryMock = $this->getMockBuilder(Query::class)
                          ->disableOriginalConstructor()
                          ->getMock();

        $testEntity = new TestEntity();
        $testEntity->setId(1);
        $testEntity->setName('John Doe');
        $testEntity->setEmail('john@example.com');
        $testEntity->setCreatedAt(new DateTime('2023-01-01 10:00:00'));
        $testEntity->setUpdatedAt(new DateTime('2023-01-02 12:00:00'));
        $testEntity->setPrice(99.99);
        $testEntity->setTags(['tag1', 'tag2']);
        $testEntity->setActive(true);

        $queryMock->method('getResult')->willReturn([$testEntity]);

        /** @var Query $queryMock */
        return $queryMock;
    }
}
