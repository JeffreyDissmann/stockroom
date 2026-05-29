# Changelog

All notable changes to Stockroom are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project uses [CalVer](https://calver.org/) versioning (`YYYY.MM.PATCH`).

## [Unreleased]

## [2026.05.06] — 2026-05-29

### Fixed

- **HomeBox import silently did nothing on every production deploy.**
  `routes/household.php` registered a legacy `Route::redirect('household/import', …)`
  in 2026.05.04 to catch stale bookmarks, sitting next to the
  `Route::post('household/import', …)` that hosts the import form.
  `Route::redirect()` is internally `Route::any()` — it matches every
  HTTP method, including POST. Under cached routes (which production
  runs — `php artisan route:cache` is in the Docker entrypoint) the
  compiled matcher picks the first-registered route, so POST hit the
  redirect and returned 302 → `/household/backup` without ever
  reaching the controller. No cache write, no job dispatch, no log,
  no error — the form just looked like nothing happened. The
  existing controller test didn't catch it because tests run with
  fresh routes, where the per-method route table makes
  `Route::post` overwrite the ANY entry. Switched the legacy
  redirect to `Route::get()` so POST falls through.

### CI

- Tests now run a **second time with `php artisan route:cache`
  applied**, mirroring production. The 2026.05.06 bug above slipped
  through CI because the first pass uses fresh routes; the second
  pass would have caught the divergence. Any future
  "works fresh, fails cached" regression now fails the build.
- New `test_post_household_import_resolves_to_the_controller_not_the_legacy_redirect`
  asserts the POST resolves to `ImportController::start`, not
  `RedirectController`, under either matcher.

## [2026.05.05] — 2026-05-29

### Fixed

- **Login appeared broken behind a reverse proxy.** On any deployment
  behind a TLS-terminating proxy (Caddy / Traefik / nginx / Cloudflare
  Tunnel — i.e. every realistic self-hosted setup) submitting the
  login form silently failed: no flash, no validation, no banner — the
  user just stayed on `/login`. Root cause: `bootstrap/app.php` never
  called `->trustProxies(at: '*')`, so Laravel ignored
  `X-Forwarded-Proto` from the proxy and saw every request as plain
  HTTP. `route()` and `redirect()->intended()` therefore generated
  `http://…` URLs, the POST `/login` redirect target became
  `http://…/dashboard`, and the browser refused that cross-scheme XHR
  from an `https://` origin as mixed content. Inertia surfaced only an
  unhelpful "AxiosError: Network Error" in the console. Identical
  pattern would have broken every other authenticated redirect
  (logout, post-create flows, etc.). Trusts any proxy by default since
  the proxy IP range is unknown in self-hosted deployments.
- **Stale "Import" entry in the mobile More menu.** When the HomeBox
  import was consolidated into the Backup screen in 2026.05.04 the
  `household.nav.import` lang key was deleted, but `BottomTabs.vue`
  kept a menu item pointing at the `/household/import` redirect — so
  the mobile dropdown rendered the raw key `household.nav.import`
  instead of a translated label.

### Tests

- New `AuthenticationTest::test_redirects_respect_x_forwarded_proto_…`
  issues a request with `X-Forwarded-Proto: https` and asserts
  `request()->isSecure()` is true and `url()->current()` starts with
  `https://` — locks the trust-proxies setup down so a future
  middleware refactor cannot silently regress login.
- Browser test asserts no raw `household.nav.*` translation keys leak
  in the mobile More dropdown.

## [2026.05.04] — 2026-05-28

### Added

- **HomeBox import is now a documented first-class feature.** The form
  has moved off its own settings page onto the existing **Household →
  Backup & import** screen, so all data-movement controls live in one
  place. Re-runs still update by HomeBox UUID, so it works as a one-shot
  migration or a recurring sync. See the new "Importing from HomeBox"
  section in the README.
- **Wipe inventory can also clear the activity log.** A new checkbox in
  the danger zone deletes every row from the activity log alongside the
  inventory — useful when starting fresh after a HomeBox import test
  run.

### Fixed

- **Backup download button was missing from the consolidated Backup &
  import screen.** `BackupRestore.vue` called `backup.exportMethod()` on
  Wayfinder's default-export object, where the key is actually `export`
  (suffixed only on the *named* exports because `export`/`import` are
  reserved at module top-level). The expression threw at render time and
  Vue silently bailed out of the subtree, leaving the rest of the page
  intact. Now uses the named exports directly.
- **`/household/import` legacy redirect 404'd.** `Route::redirect`
  treats a bare path as relative, so from `/household/import` the
  destination resolved to `/household/household/backup`. Fixed with the
  leading slash.
- **Caddyfile boot warning ("input is not formatted")** — ran
  `frankenphp fmt --overwrite` on `docker/caddy/Caddyfile` to switch to
  the canonical tab-indent style. No semantic change; just removes the
  warning that printed on every container boot.
- **Mobile assistant FAB leaked onto desktop.** A scoped `<style>` rule
  set `display: inline-flex` on the button with `.assistant-fab[data-v-…]`
  specificity, which beat Tailwind's `.md\:hidden` and made the floating
  shortcut visible at every breakpoint. Moved the `display` declaration
  to Tailwind utility classes so the breakpoint rule wins. New browser
  test asserts the FAB is missing on desktop.

### Tests

- New `tests/Browser/HouseholdTest.php` exercises every Household
  subpage with `assertNoJavaScriptErrors()` plus presence assertions
  for the backup download button and the wipe-flow checkboxes. Both
  bugs above were caught by these tests and would not regress silently
  again. CLAUDE.md now documents the "every screen gets a render
  canary" + "every action button gets a `data-test`" convention.

- **Queue worker OOM on large photo imports**: PHP's `memory_limit` in
  the Docker image was 256 MB — comfortable for web requests but too
  tight for `intervention/image` (GD) to decode a 12+ MP photo, which
  needs ~200 MB just to decompress. The HomeBox import job tripped
  this immediately and bounced every job to `failed_jobs` after
  `MaxAttemptsExceeded`. Bumped to 512 MB, which handles photos up to
  ~80 MP with headroom.

## [2026.05.03] — 2026-05-28

### Fixed

- **First-boot 500 ("MissingAppKeyException")**: the entrypoint generated
  the APP_KEY into `.env`, but Laravel's Dotenv treats OS env vars as
  immutable, so an empty `APP_KEY=` line from `.env.docker.example` (passed
  via compose `env_file:`) overrode it on every request. The key is now
  generated once with `key:generate --show`, persisted to
  `storage/app/.stockroom-app-key` (a volume-mounted location that survives
  container recreation), and exported into the runtime env before
  FrankenPHP starts. Existing installs continue to work; a new key is
  generated only if neither the env nor the key file provide one.
- **404 on every non-file URL**: the Caddyfile wrapped `php_server` in a
  block with a custom `try_files`, which silently overrode FrankenPHP's
  built-in Laravel rewrite so requests like `/login` or `/up` returned
  an empty Caddy 404 instead of reaching PHP. Replaced with bare
  `php_server`.
- **Containers reported as `(unhealthy)`**: the app healthcheck used
  `wget`, which the FrankenPHP base image doesn't ship; switched to
  `curl`, which it does. Meilisearch's healthcheck override has been
  removed (the image is FROM scratch with no shell to run one); the
  app's dependency on it is now `service_started` rather than
  `service_healthy`, since search access is lazy at request time.

### CI

- Release workflow rewritten to build amd64 and arm64 on native runners
  in parallel (no QEMU), then compose the multi-arch manifest in a merge
  job. Typical release build time drops from ~12 min to ~4 min.

## [2026.05.02] — 2026-05-28

### Fixed

- **Docker image**: `docker/php/php.ini` used `#` for comments, which PHP's
  INI parser rejects. The file silently failed to load on every PHP
  invocation, leaving the production opcache tuning and 32 MB upload limit
  unapplied (so large image uploads from a phone could 413). Replaced all
  `#` comments with the canonical `;`. Reported on first UGREEN NAS deploy.

## [2026.05.01] — 2026-05-28

First public release.

### Added

- **Inventory model** — items, containers and rooms in a single recursive tree;
  every item belongs to one household and inherits permissions from it.
- **Multi-user households** — invite-only registration via single-use token links;
  admins and members share one household.
- **Custom fields** — household-wide field definitions (text, number, date,
  boolean, URL) attached per item, searchable when flagged.
- **Tags** — coloured labels with item counts; admin-only creation/editing.
- **Image management** — multiple images per item with thumbs / large / original
  variants, primary image, drag-to-reorder, "search for an image" via Brave
  Search.
- **Hybrid search** — keyword + semantic (vector) search via Meilisearch and
  app-side embeddings (Laravel AI SDK, `userProvided` vectors).
- **AI assistant** — multi-turn chat (slide-over panel + floating mobile FAB)
  backed by local Ollama with read/write tools (`SearchItems`, `GetItem`,
  `InventoryStats`, `CreateItem`, `UpdateItem`, `MoveItem`, `AssignTags`,
  `DeleteItem`). Conversations persist and are forgotten after a configurable
  retention window.
- **Activity log** — every create/update/delete and image upload recorded per
  user.
- **Localization** — German and English UI with per-user locale preference.
- **System settings** — backup/restore the household to a single archive.
- **Typed frontend routes** — Laravel Wayfinder generates a TypeScript route
  tree; CI guards against drift.

[Unreleased]: https://github.com/JeffreyDissmann/stockroom/compare/2026.05.06...HEAD
[2026.05.06]: https://github.com/JeffreyDissmann/stockroom/compare/2026.05.05...2026.05.06
[2026.05.05]: https://github.com/JeffreyDissmann/stockroom/compare/2026.05.04...2026.05.05
[2026.05.04]: https://github.com/JeffreyDissmann/stockroom/compare/2026.05.03...2026.05.04
[2026.05.03]: https://github.com/JeffreyDissmann/stockroom/compare/2026.05.02...2026.05.03
[2026.05.02]: https://github.com/JeffreyDissmann/stockroom/compare/2026.05.01...2026.05.02
[2026.05.01]: https://github.com/JeffreyDissmann/stockroom/releases/tag/2026.05.01
