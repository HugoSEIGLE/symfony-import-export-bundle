# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and releases use semantic versioning.

## [Unreleased]

## [2.1.3] - 2026-08-16

### Fixed

- Importer now schedules Doctrine persistence for created entities during import.
- Importer now schedules Doctrine removals for deleted entities during import.

### Changed

- Demo and example import controllers now only call `flush()` after a successful import.

### Documentation

- Updated import behavior documentation to clarify that `persist()` and `remove()` are scheduled by the importer while `flush()` remains application-controlled.

### Tests

- Added importer regression coverage to assert `persist()` is called for created entities and `remove()` is called for deleted entities.

## [2.0.1] - 2026-07-15

### Fixed

- Made the Composer lint script read-only so CI does not rewrite source files.
- Aligned the XML schema namespace and optional defaults with the bundle configuration tree.
- Declared Symfony's CSRF and YAML components, which provide the form option and service-file loader used directly by the bundle.

### Added

- Added a coherent Doctrine `Company` import/export example with a Symfony form and structured error handling.
- Added bundle-extension, normalized-configuration, and service-registration tests.
- Added issue forms, pull request guidance, security policy, contribution guide, and release checklist.
- Added a supported PHP/Symfony CI matrix and static analysis workflow.

### Changed

- Expanded the Symfony 7 constraint from `^7.1` to `^7.0`, matching the documented 7.x compatibility target.
- Added explicit Composer scripts for read-only coding-standard checks and PHPStan.

### Documentation

- Reworked the README around installation, first import/export, errors, formats, customization, compatibility, and quality commands.
- Added detailed installation, import, export, validation, customization, 1.x upgrade, and release guides.

### Upgrade notes from 1.x

- The former `SymfonyImportExportBundle\` namespace is not aliased; consumers must use `HugoSEIGLE\SymfonyImportExportBundle\`.
- Import calls return `ImportResult`; the former stateful importer result methods are removed.
- Concrete importer/exporter constructor changes affect manual instantiation. Interface-based dependency injection remains recommended.

[Unreleased]: https://github.com/HugoSEIGLE/symfony-import-export-bundle/compare/v2.1.3...HEAD
[2.1.3]: https://github.com/HugoSEIGLE/symfony-import-export-bundle/compare/v2.0.1...v2.1.3
[2.0.1]: https://github.com/HugoSEIGLE/symfony-import-export-bundle/compare/v2.0.0...v2.0.1
