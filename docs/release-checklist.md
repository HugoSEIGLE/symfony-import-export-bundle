# Release checklist

1. Choose the target version (for example `v2.1.3`) and update the changelog date and release notes.
2. Confirm CI is green on every supported PHP/Symfony combination.
3. Confirm the target tag does not already exist locally or remotely. Never move or rewrite a published tag silently.
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
git tag -a vX.Y.Z -m "Release vX.Y.Z"
git push origin vX.Y.Z
```

6. Create the GitHub release from the matching document in `docs/` (for example `docs/github-release-v2.1.3.md`).
7. Verify the GitHub/Packagist webhook ran, or trigger “Update” on Packagist.
8. Install `hugoseigle/symfony-import-export-bundle:^2.0` in a clean Symfony application as a smoke test.
