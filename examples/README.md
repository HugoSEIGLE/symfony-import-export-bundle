# Company example

These files form one coherent Symfony application example. Copy them into the matching `src/` and `config/` directories of an application, adjust the namespaces if needed, create a Doctrine migration, and add routes through Symfony attribute routing.

The import endpoint expects a `multipart/form-data` POST with a `file` field. Its CSV header is:

```csv
name,email,active
```

The importer validates each row through `CompanyImportType` and returns structured errors without writing anything when one or more rows are invalid. The application, not the bundle, owns persistence and transaction policy.

The export endpoint returns the same business columns as CSV. Change `ExporterInterface::CSV` to `ExporterInterface::XLSX` to generate Excel instead.

See the root [README](../README.md) and [import documentation](../docs/import.md) for configuration details.
