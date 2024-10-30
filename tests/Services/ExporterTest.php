<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Tests\Services;

use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use SymfonyImportExportBundle\Services\Exporter;
use SymfonyImportExportBundle\Services\ExporterInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\CssSelector\XPath\TranslatorInterface;
use SymfonyImportExportBundle\Tests\Entity\TestEntity;

class ExporterTest extends TestCase
{
    private ExporterInterface $exporter;
    private TranslatorInterface $translator;

    protected function setUp(): void
    {
        $spreadsheet = new Spreadsheet();
        $this->exporter = new Exporter($spreadsheet);
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

        $this->assertEquals('id', $sheet->getCell('A1')->getValue());
        $this->assertEquals('name', $sheet->getCell('B1')->getValue());
        $this->assertEquals('email', $sheet->getCell('C1')->getValue());
        $this->assertEquals('createdAt', $sheet->getCell('D1')->getValue());
        $this->assertEquals('updatedAt', $sheet->getCell('E1')->getValue());
        $this->assertEquals('price', $sheet->getCell('F1')->getValue());
        $this->assertEquals('tags', $sheet->getCell('G1')->getValue());
        $this->assertEquals('isActive', $sheet->getCell('H1')->getValue());

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

        $expectedCsv = "getId,getName,getEmail,getCreatedAt,getUpdatedAt,getPrice,getTags,isActive\n"
                     . "1,\"John Doe\",\"john@example.com\",\"2023-01-01 10:00:00\",\"2023-01-02 12:00:00\",99.99,\"tag1, tag2\",\"true\"\n";

        $this->assertEquals($expectedCsv, $csv);

        unlink($tempFilePath);
    }

    private function getMethods(): array {
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
        $testEntity->setCreatedAt(new \DateTime('2023-01-01 10:00:00'));
        $testEntity->setUpdatedAt(new \DateTime('2023-01-02 12:00:00'));
        $testEntity->setPrice(99.99);
        $testEntity->setTags(['tag1', 'tag2']);
        $testEntity->setActive(true);

        $queryMock->method('getResult')->willReturn([$testEntity]);

        /** @var Query $queryMock */
        return $queryMock;
    }
}
