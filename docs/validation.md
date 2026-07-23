# Validation and errors

Keep `validate_headers: true` for predictable production imports. Header errors stop processing because column meaning would be ambiguous. Wrong column counts, invalid booleans/dates/enums, form violations, missing deletion targets, and forbidden operations become structured `ImportError` objects.

```php
if (!$result->isValid()) {
    foreach ($result->getErrors() as $error) {
        // $error->row, $error->field, $error->message, $error->value
    }
}
```

The bundle continues after row-level errors and may return valid candidates alongside errors. Choose an application policy explicitly. For atomic imports, persist candidates only when `isValid()` is true and wrap persistence/removal in a Doctrine transaction. Validate upload size, extension, and MIME type at the HTTP boundary.

An empty file is invalid. A file containing only a valid header is a valid import with no candidates. Unsupported filename extensions raise `InvalidArgumentException`.
