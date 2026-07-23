# Exporting

The exporter accepts a Doctrine ORM `Query`, an ordered list of public entity methods, a base filename, and `csv` or `xlsx`.

```php
return $exporter->export(
    $companyRepository->createQueryBuilder('company')->getQuery(),
    ['getName', 'getEmail', 'isActive'],
    'companies',
    ExporterInterface::CSV,
);
```

Each method becomes a column. Its header is translated in the `messages` domain: `getFoundedAt` maps to `import_export.get_founded_at`. Values may be null, scalar, boolean, `DateTimeInterface`, arrays, Doctrine collections, or stringable objects. Unsupported objects and missing methods raise `InvalidArgumentException`.

CSV uses `Query::toIterable()` and writes rows directly to the response stream. XLSX also iterates the query, but PhpSpreadsheet retains workbook cells in memory. Empty queries generate a header-only file.

Output formatting uses the shared `date_format`, boolean token, and CSV settings. Generate Excel with `ExporterInterface::XLSX`.
