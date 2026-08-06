<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Tests\Benchmarks;

use HugoSEIGLE\SymfonyImportExportBundle\Benchmarks\DatasetGenerator;
use HugoSEIGLE\SymfonyImportExportBundle\Benchmarks\TemporaryWorkspace;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;

use function file;

final class DatasetGeneratorTest extends TestCase
{
    public function testGeneratedRowsAreDeterministic(): void
    {
        self::assertSame([
            'name' => 'Company 000042',
            'email' => 'company-000042@example.test',
            'employees' => 42,
            'active' => true,
        ], (new DatasetGenerator())->row(42));
    }

    public function testCsvAndXlsxContainTheRequestedRows(): void
    {
        $workspace = TemporaryWorkspace::create();

        try {
            $csv = $workspace->file('dataset.csv');
            $xlsx = $workspace->file('dataset.xlsx');
            $generator = new DatasetGenerator();
            $generator->createImportFile($csv, 'csv', 2);
            $generator->createImportFile($xlsx, 'xlsx', 2);

            self::assertCount(3, file($csv));
            $spreadsheet = IOFactory::load($xlsx);

            try {
                self::assertSame(3, $spreadsheet->getActiveSheet()->getHighestDataRow());
                self::assertSame('Company 000002', $spreadsheet->getActiveSheet()->getCell('A3')->getValue());
                self::assertSame('false', $spreadsheet->getActiveSheet()->getCell('D2')->getValue());
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        } finally {
            $workspace->cleanup();
        }
    }

    public function testXlsxPreservesZeroEmployees(): void
    {
        $workspace = TemporaryWorkspace::create();

        try {
            $xlsx = $workspace->file('dataset.xlsx');
            (new DatasetGenerator())->createImportFile($xlsx, 'xlsx', 1000);
            $spreadsheet = IOFactory::load($xlsx);

            try {
                self::assertSame(0, $spreadsheet->getActiveSheet()->getCell('C1001')->getValue());
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        } finally {
            $workspace->cleanup();
        }
    }
}
