<?php

declare(strict_types=1);

namespace HugoSEIGLE\SymfonyImportExportBundle\Benchmarks;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use HugoSEIGLE\SymfonyImportExportBundle\Services\Export\Exporter;
use HugoSEIGLE\SymfonyImportExportBundle\Services\Import\Importer;
use HugoSEIGLE\SymfonyImportExportBundle\Services\MethodToSnake;
use RuntimeException;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

use function count;
use function dirname;
use function fclose;
use function fopen;
use function function_exists;
use function fwrite;
use function gc_collect_cycles;
use function hrtime;
use function is_array;
use function is_int;
use function is_resource;
use function is_string;
use function json_decode;
use function memory_get_peak_usage;
use function memory_reset_peak_usage;
use function ob_end_clean;
use function ob_get_level;
use function ob_start;
use function proc_close;
use function proc_open;
use function sprintf;
use function stream_get_contents;
use function trim;

use const JSON_THROW_ON_ERROR;
use const PHP_BINARY;
use const PHP_VERSION_ID;

final class BenchmarkRunner
{
    private const FIELDS = ['name', 'email', 'employees', 'active'];
    private const METHODS = ['getName', 'getEmail', 'getEmployees', 'isActive'];

    public function __construct(private readonly DatasetGenerator $datasets = new DatasetGenerator())
    {
    }

    /** @return list<BenchmarkResult> */
    public function run(BenchmarkOptions $options): array
    {
        $results = [];

        foreach ($options->operations as $operation) {
            foreach ($options->formats as $format) {
                foreach ($options->rows as $rows) {
                    $durations = [];
                    $peakMemories = [];
                    for ($run = 0; $run < $options->runs; ++$run) {
                        $sample = $this->runIsolated($operation, $format, $rows);
                        $durations[] = $sample->durationNanoseconds;
                        $peakMemories[] = $sample->peakMemoryBytes;
                    }

                    $statistics = new Statistics();
                    $results[] = new BenchmarkResult(
                        $operation,
                        $format,
                        $rows,
                        $statistics->median($durations),
                        $statistics->median($peakMemories),
                        $options->runs,
                    );
                }
            }
        }

        return $results;
    }

    public function runScenario(string $operation, string $format, int $rows): BenchmarkResult
    {
        $workspace = TemporaryWorkspace::create();

        try {
            return 'import' === $operation
                ? $this->import($workspace, $format, $rows)
                : $this->export($workspace, $format, $rows);
        } finally {
            $workspace->cleanup();
        }
    }

    private function runIsolated(string $operation, string $format, int $rows): BenchmarkResult
    {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/worker.php', $operation, $format, (string) $rows],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start an isolated benchmark process.');
        }

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if (0 !== $exitCode) {
            throw new RuntimeException(trim($error) ?: 'The isolated benchmark process failed.');
        }

        $data = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data) ||
            !isset($data['operation'], $data['format'], $data['rows'], $data['duration_nanoseconds'], $data['peak_memory_bytes']) ||
            !is_string($data['operation']) ||
            !is_string($data['format']) ||
            !is_int($data['rows']) ||
            !is_int($data['duration_nanoseconds']) ||
            !is_int($data['peak_memory_bytes'])
        ) {
            throw new RuntimeException('The isolated benchmark returned an invalid result.');
        }

        return new BenchmarkResult(
            $data['operation'],
            $data['format'],
            $data['rows'],
            $data['duration_nanoseconds'],
            $data['peak_memory_bytes'],
        );
    }

    private function import(TemporaryWorkspace $workspace, string $format, int $rows): BenchmarkResult
    {
        $input = $workspace->file(sprintf('import-%s-%d.%s', $format, $rows, $format));
        $database = $workspace->file(sprintf('import-%s-%d.sqlite', $format, $rows));
        $this->datasets->createImportFile($input, $format, $rows);
        $entityManager = $this->entityManager($database);

        try {
            $importer = $this->importer($entityManager);
            $file = new UploadedFile($input, 'benchmark.' . $format, null, null, true);

            $this->resetPeakMemory();
            $start = hrtime(true);
            $result = $importer->import($file, BenchmarkEntity::class, BenchmarkEntityType::class);
            $duration = hrtime(true) - $start;
            $peakMemory = memory_get_peak_usage(true);

            if (!$result->isValid() || $rows !== count($result->getCreatedEntities())) {
                $firstError = $result->getErrors()[0] ?? null;

                throw new RuntimeException(sprintf(
                    'The %s import benchmark produced %d/%d entities%s.',
                    $format,
                    count($result->getCreatedEntities()),
                    $rows,
                    null === $firstError ? '' : sprintf(' (row %d: %s)', $firstError->row, $firstError->message),
                ));
            }
        } finally {
            $entityManager->getConnection()->close();
        }

        return new BenchmarkResult('import', $format, $rows, $duration, $peakMemory);
    }

    private function export(TemporaryWorkspace $workspace, string $format, int $rows): BenchmarkResult
    {
        $database = $workspace->file(sprintf('export-%s-%d.sqlite', $format, $rows));
        $output = $workspace->file(sprintf('export-%s-%d.%s', $format, $rows, $format));
        $entityManager = $this->entityManager($database);

        try {
            $this->seed($entityManager, $rows);
            $exporter = new Exporter(new BenchmarkTranslator(), new MethodToSnake());
            $query = $entityManager->createQueryBuilder()
                ->select('entity')
                ->from(BenchmarkEntity::class, 'entity')
                ->orderBy('entity.id', 'ASC')
                ->getQuery();
            $handle = fopen($output, 'wb');
            if (false === $handle) {
                throw new RuntimeException(sprintf('Unable to create %s.', $output));
            }

            $bufferLevel = ob_get_level();
            ob_start(static function (string $buffer) use ($handle): string {
                fwrite($handle, $buffer);

                return '';
            }, 8192);

            try {
                $this->resetPeakMemory();
                $start = hrtime(true);
                $response = $exporter->export($query, self::METHODS, 'benchmark', $format);
                $response->sendContent();
                $duration = hrtime(true) - $start;
                $peakMemory = memory_get_peak_usage(true);
            } finally {
                while (ob_get_level() > $bufferLevel) {
                    ob_end_clean();
                }
                fclose($handle);
            }
        } finally {
            $entityManager->getConnection()->close();
        }

        return new BenchmarkResult('export', $format, $rows, $duration, $peakMemory);
    }

    private function entityManager(string $database): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([__DIR__], true);
        if (PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $database], $configuration);
        $entityManager = new EntityManager($connection, $configuration);
        (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(BenchmarkEntity::class)]);

        return $entityManager;
    }

    private function importer(EntityManager $entityManager): Importer
    {
        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension(new CsrfTokenManager()))
            ->addType(new BenchmarkEntityType())
            ->getFormFactory();

        return new Importer(
            $entityManager,
            $formFactory,
            new BenchmarkTranslator(),
            [BenchmarkEntity::class => ['fields' => self::FIELDS, 'unique_fields' => []]],
            methodToSnake: new MethodToSnake(),
        );
    }

    private function seed(EntityManager $entityManager, int $rows): void
    {
        for ($index = 1; $index <= $rows; ++$index) {
            $row = $this->datasets->row($index);
            $entity = (new BenchmarkEntity())
                ->setId($index)
                ->setName($row['name'])
                ->setEmail($row['email'])
                ->setEmployees($row['employees'])
                ->setActive($row['active']);
            $entityManager->persist($entity);

            if (0 === $index % 500) {
                $entityManager->flush();
                $entityManager->clear();
            }
        }
        $entityManager->flush();
        $entityManager->clear();
    }

    private function resetPeakMemory(): void
    {
        gc_collect_cycles();
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
    }
}
