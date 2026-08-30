---
paths:
  - pint.json
---

# General

## Pint must exclude the .nosync directories or it rewrites your dependencies
`vendor` and `node_modules` are symlinks to `vendor.nosync` / `node_modules.nosync` (an iCloud eviction workaround). Pint excludes `vendor` by default, but that never matches the real directory name, so a full `vendor/bin/pint` run reformats the dependencies — 1703 files, including Mockery's own source, which breaks every test that mocks anything with `Interface "MockInterface" not found`.

`composer install` does not repair it: the packages are still installed, so it will not overwrite modified files. Only wiping vendor and reinstalling does, and that must be driven through `docker compose exec` because emptying vendor breaks `vendor/bin/sail` itself.

Keep both `.nosync` paths in pint.json's `exclude`. A full check should take about two seconds; if it appears to hang, it is scanning the dependencies.
