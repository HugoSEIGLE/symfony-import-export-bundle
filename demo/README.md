# Demo application

This Symfony 6.4 application demonstrates the bundle with a small company directory backed by SQLite.

## Run it

```bash
composer install
composer setup
symfony serve
```

Open the URL printed by Symfony CLI. `composer setup` creates the SQLite schema and loads five example companies; it is safe to run again.

Use [`data/companies.csv`](data/companies.csv) to try an import. The interface also provides empty CSV/XLSX templates and full CSV/XLSX exports.

The bundle is installed from the parent directory through a Composer `path` repository, so local bundle changes are immediately available in the demo.
