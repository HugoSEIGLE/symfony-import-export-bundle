<?php

declare(strict_types=1);

namespace SymfonyImportExportBundle\Tests\Services;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use SymfonyImportExportBundle\Services\Exporter;
use SymfonyImportExportBundle\Services\ExporterInterface;

use function file_get_contents;
use function file_put_contents;
use function ob_get_clean;
use function ob_start;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

class ExporterTest extends TestCase implements ExporterTestInterface
{
    private ExporterInterface $exporter;

    protected function setUp(): void
    {
        $spreadsheet = new Spreadsheet();
        $this->exporter = new Exporter($spreadsheet);
    }

    public function testExportToXlsxGeneratesCorrectResponse(): void
    {
        $queryBuilder = $this->createQueryBuilder();

        $fields = ['id', 'name', 'email'];

        $response = $this->exporter->exportXlsx($queryBuilder, $fields, 'export');

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
        $this->assertEquals(1, $sheet->getCell('A2')->getValue());
        $this->assertEquals('John Doe', $sheet->getCell('B2')->getValue());
        $this->assertEquals('john@example.com', $sheet->getCell('C2')->getValue());

        unlink($tempFilePath);
    }

    public function testExportToCsvGeneratesCorrectResponse(): void
    {
        $queryBuilder = $this->createQueryBuilder();

        $fields = ['id', 'name', 'email'];

        $response = $this->exporter->exportCsv($queryBuilder, $fields, 'export');

        $this->assertInstanceOf(StreamedResponse::class, $response);

        $tempFilePath = tempnam(sys_get_temp_dir(), 'export') . '.csv';

        ob_start();

        $response->sendContent();

        $content = ob_get_clean();

        file_put_contents($tempFilePath, $content);

        $this->assertFileExists($tempFilePath);

        $csv = file_get_contents($tempFilePath);

        $this->assertEquals("id,name,email\n1,\"John Doe\",john@example.com\n", $csv);

        unlink($tempFilePath);
    }

    private function createQueryBuilder(): QueryBuilder
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
                             ->setConstructorArgs([$entityManager])
                             ->getMock();

        $queryMock = $this->getMockBuilder(Query::class)
                          ->disableOriginalConstructor()
                          ->getMock();

        $queryMock->method('getArrayResult')->willReturn([
            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
        ]);

        $queryBuilder->expects($this->once())
                     ->method('getQuery')
                     ->willReturn($queryMock);

        /** @var QueryBuilder $queryBuilder */
        return $queryBuilder;
    }
}
