# Release checklist

1. Confirm the changelog date and release notes.
2. Confirm CI is green on every supported PHP/Symfony combination.
3. Confirm `v2.0.1` does not already exist locally or remotely. This repository currently has such a tag, so reconcile it before attempting to publish this release; never move a published tag silently.
4. Run:

```bash
composer validate --strict
composer normalize --dry-run
composer dump-autoload --optimize --strict-psr
composer test
composer lint
composer phpstan
git diff --check
git status
```

5. After committing the reviewed release changes, create and push the tag only if the tag name is available:

```bash
git tag -a v2.0.1 -m "Release v2.0.1"
git push origin v2.0.1
```

6. Create the GitHub release from `docs/github-release-v2.0.1.md`.
7. Verify the GitHub/Packagist webhook ran, or trigger “Update” on Packagist.
8. Install `hugoseigle/symfony-import-export-bundle:^2.0` in a clean Symfony application as a smoke test.
