# Performance benchmarks

The benchmark suite makes import/export performance comparable between bundle commits. It executes the public `Importer` and `Exporter` services with deterministic entities, a real SQLite-backed Doctrine `Query`, Symfony forms, and actual CSV/XLSX files. It does not require an HTTP server and does not duplicate the bundle's parsing or serialization logic.

## Run the benchmarks

Install the project dependencies, then run every scenario with 100, 1,000, 10,000, and 50,000 rows:

```bash
composer benchmark
```

Use a small dataset for a quick smoke test:

```bash
composer benchmark -- --rows=100
```

Filters can isolate a regression:

```bash
composer benchmark -- --rows=1000 --format=csv
composer benchmark -- --rows=1000 --format=xlsx --operation=import
composer benchmark -- --rows=100,1000 --operation=export
```

The accepted formats are `csv`, `xlsx`, or `all`; operations are `import`, `export`, or `all`. Row counts may be a single positive integer or a comma-separated list.

The 50,000-row scenarios are intentionally included to expose streaming behavior and XLSX memory growth. Run a filtered or smaller dataset first when the available memory is limited.

Each scenario runs three times by default. Change the repetition count when exploring locally, while keeping the same value on both versions being compared:

```bash
composer benchmark -- --rows=1000 --runs=5
```

## Save results

The terminal table can also be saved as JSON or CSV. The exported file includes the environment alongside every measurement so results remain attributable:

```bash
mkdir -p benchmarks/results
composer benchmark -- --rows=1000 --output=benchmarks/results/baseline.json
composer benchmark -- --rows=1000 --output=benchmarks/results/candidate.csv
```

`benchmarks/results/` is ignored by Git. Copy results elsewhere if they need to be retained or published.

## Scenarios

The runner measures these four scenarios:

| Operation | Format | Work performed during measurement |
| --- | --- | --- |
| Import | CSV | `Importer::import()` parses the generated upload and submits every row through a Symfony form. |
| Import | XLSX | `Importer::import()` loads the workbook through PhpSpreadsheet, then submits every row. |
| Export | CSV | `Exporter::export()` creates and streams the response into a real temporary CSV file. |
| Export | XLSX | `Exporter::export()` creates and writes a real temporary XLSX workbook through PhpSpreadsheet. |

Import datasets represent creations and use no unique lookup fields. Export datasets are inserted into SQLite before measurement and read through a real Doctrine ORM query. Dataset generation, schema creation, and export database seeding are deliberately outside the measured duration. Each scenario runs in an isolated PHP process so prior XLSX allocations cannot affect later peak-memory results. Temporary input, output, and SQLite files are always deleted in a `finally` block.

## Measurements

- **Rows** is the number of data rows, excluding the header.
- **Median time** is the median elapsed wall-clock time around the public service call and response streaming, measured with `hrtime(true)` over the configured isolated runs.
- **Peak memory** is the median of the per-run `memory_get_peak_usage(true)` values. On PHP versions providing `memory_reset_peak_usage()`, the previous setup peak is reset before timing. On older PHP versions, each value is the process peak and may include setup; the environment output reports whether reset is supported.

The runner displays:

- PHP version;
- Symfony version;
- Doctrine ORM version;
- PhpSpreadsheet version;
- operating system;
- CPU when `/proc/cpuinfo` is available;
- PHP memory limit;
- current bundle commit SHA when Git is available.

## Comparing results

Use the same machine, PHP binary, dependency lock file, memory limit, power profile, row selection, and `--runs` value for both commits. Close unrelated workloads and compare medians rather than individual samples. Record whether extensions such as Xdebug are enabled.

CSV export is streamed and should avoid retaining the complete output in memory. Imports retain candidate entities in `ImportResult`. XLSX import and export depend on PhpSpreadsheet, which loads or builds workbook cells in memory; large XLSX datasets therefore require substantially more memory than streamed CSV and may hit the configured PHP memory limit.

## Published results

Performance figures depend on the machine and on the installed PHP, Symfony, Doctrine ORM, and PhpSpreadsheet versions; results from different environments are not directly comparable.

The following baseline is the median of three isolated executions per scenario from `composer benchmark` on 2026-08-06. Peak memory is also the median of the three per-process peaks. Bundle base commit: `848b9fc`; the working tree included the benchmark implementation documented on this page.

| Environment | Value |
| --- | --- |
| PHP | 8.5.3 |
| Symfony | 8.1.1 |
| Doctrine ORM | 3.6.7 |
| PhpSpreadsheet | 2.4.7 |
| Operating system | Linux 6.12.73+deb13-amd64 |
| CPU | 13th Gen Intel Core i7-1355U |
| PHP memory limit | Unlimited (`-1`) |
| Peak reset | Supported |

| Operation | Format | Rows | Median time | Peak memory |
| --- | --- | ---: | ---: | ---: |
| Import | CSV | 100 | 0.027 s | 16.0 MB |
| Import | CSV | 1,000 | 0.209 s | 16.0 MB |
| Import | CSV | 10,000 | 2.109 s | 18.0 MB |
| Import | CSV | 50,000 | 12.647 s | 28.0 MB |
| Import | XLSX | 100 | 0.041 s | 22.0 MB |
| Import | XLSX | 1,000 | 0.300 s | 24.0 MB |
| Import | XLSX | 10,000 | 3.422 s | 69.0 MB |
| Import | XLSX | 50,000 | 25.454 s | 202.0 MB |
| Export | CSV | 100 | 0.005 s | 12.0 MB |
| Export | CSV | 1,000 | 0.011 s | 12.0 MB |
| Export | CSV | 10,000 | 0.063 s | 24.0 MB |
| Export | CSV | 50,000 | 0.318 s | 67.5 MB |
| Export | XLSX | 100 | 0.035 s | 34.0 MB |
| Export | XLSX | 1,000 | 0.097 s | 36.0 MB |
| Export | XLSX | 10,000 | 0.810 s | 73.0 MB |
| Export | XLSX | 50,000 | 4.055 s | 208.6 MB |

This local baseline is not a performance guarantee. The progression is broadly consistent with the expected workload: CSV remains faster and substantially lighter, while XLSX memory rises with the number of workbook cells retained by PhpSpreadsheet. Nothing in this baseline alone indicates a catastrophic regression requiring bundle optimization. Repeat the run before drawing conclusions about small differences between versions.
