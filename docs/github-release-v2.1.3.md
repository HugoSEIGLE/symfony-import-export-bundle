## Symfony Import Export Bundle 2.1.3

This patch release streamlines import persistence behavior and keeps application-level transaction control explicit.

### Highlights

- Created entities are now scheduled with Doctrine `persist()` during import.
- Deleted entities are now scheduled with Doctrine `remove()` during import.
- Applications now complete successful imports with a single `flush()` call.
- Demo and example controllers were aligned with the new flow.
- Documentation and tests were updated to reflect and verify the behavior.

### Installation

```bash
composer require hugoseigle/symfony-import-export-bundle:^2.0
```

### Notes

`ImporterInterface::import()` still returns `ImportResult` and never calls `flush()`. Keep your transaction policy in the application, and call `flush()` only when your import validity policy allows it.
