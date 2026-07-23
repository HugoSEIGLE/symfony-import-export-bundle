# Customization

## Global options

```yaml
import_export:
    date_format: 'Y-m-d H:i:s'
    bool_true: 'yes'
    bool_false: 'no'
    validate_headers: true
    csv:
        delimiter: ';'
        enclosure: '"'
        escape: ''
        bom: true
```

`delimiter` and `enclosure` must be exactly one character. `escape` may contain zero or one character. The same CSV options apply to imports, exports, and templates.

An importer may override global header validation:

```yaml
import_export:
    importers:
        App\Entity\Company:
            fields: [name, email]
            unique_fields: [email]
            validate_headers: false
```

## Translated headers

Add keys to the application `messages` catalogue:

```yaml
import_export.name: Company name
import_export.email: Email address
```

`MethodToSnakeInterface` converts camelCase names to snake_case for keys. Its service can be decorated if an application needs another naming strategy. The bundle currently dispatches no events; customization is through configuration, Symfony forms/data transformers, translations, runtime operation flags, and normal service decoration.
