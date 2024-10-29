<?php

namespace SymfonyImportExportBundle\Tests\Services;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use SymfonyImportExportBundle\Services\Exporter;
use SymfonyImportExportBundle\Services\ExporterInterface;

class ExporterTest extends TestCase
{
    private ExporterInterface $exporter;

    protected function setUp(): void
    {
        $spreadsheet = new Spreadsheet();
        $this->exporter = new Exporter($spreadsheet);
    }

    public function testExportToXlsxGeneratesCorrectResponse(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
                             ->setConstructorArgs([$entityManager])
                             ->getMock();

        $queryMock = $this->getMockBuilder(Query::class)
                          ->disableOriginalConstructor()
                          ->getMock();

        $queryMock->method('getArrayResult')->willReturn([
            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com']
        ]);

        $queryBuilder->expects($this->once())
                     ->method('getQuery')
                     ->willReturn($queryMock);

        $fields = ['id', 'name', 'email'];

        $response = $this->exporter->export($queryBuilder, $fields);

        $this->assertInstanceOf(StreamedResponse::class, $response);

        $tempFilePath = tempnam(sys_get_temp_dir(), 'export') . '.xlsx';

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        file_put_contents($tempFilePath, $content);

        $this->assertFileExists($tempFilePath);

        $spreadsheet = IOFactory::load($tempFilePath);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertEquals('Id', $sheet->getCell('A1')->getValue());
        $this->assertEquals('Name', $sheet->getCell('B1')->getValue());
        $this->assertEquals('Email', $sheet->getCell('C1')->getValue());
        $this->assertEquals(1, $sheet->getCell('A2')->getValue());
        $this->assertEquals('John Doe', $sheet->getCell('B2')->getValue());
        $this->assertEquals('john@example.com', $sheet->getCell('C2')->getValue());

        unlink($tempFilePath);
    }
}
