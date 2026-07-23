# Contributing

Thank you for helping improve the bundle. Keep changes focused, preserve supported PHP/Symfony versions, and add tests for externally visible behavior.

## Local setup

```bash
git clone git@github.com:HugoSEIGLE/symfony-import-export-bundle.git
cd symfony-import-export-bundle
composer install
```

Before opening a pull request, run:

```bash
composer validate --strict
composer normalize --dry-run
composer dump-autoload --optimize --strict-psr
composer test
composer lint
composer phpstan
git diff --check
```

`composer lint` checks formatting without modifying files. To apply formatting intentionally, run `vendor/bin/php-cs-fixer fix`, review the diff, and rerun the checks.

Document public behavior and update `CHANGELOG.md` under `Unreleased`. Do not add a production dependency without explaining why existing dependencies or the standard library cannot meet the requirement. Never include real imported data, credentials, or vulnerability details in tests or public issues.

By submitting a contribution, you agree that it is licensed under the project's MIT License.
