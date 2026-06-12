# Changelog

All notable changes to Stockroom are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project uses [CalVer](https://calver.org/) versioning (`YYYY.MM.PATCH`).

## [Unreleased]

## [2026.06.07] — 2026-06-12

### Added

- **Battery tracking.** Items can now track a battery. Set a **battery type**
  (`AA`, `CR2032`, `AA ×4`, …) on the item, and Stockroom keeps a per-battery
  history of level readings. It keeps one *cycle* per physical battery,
  auto-detects a swap from a low→full jump in the readings, and fits a
  depletion line — pooled across the last few batteries so a fresh one is
  forecast from how its predecessors drained — to predict when the level will
  hit the low threshold. That prediction drives a system-managed **"Replace
  battery"** maintenance reminder, so the dashboard card, the daily digest and
  the maintenance API all light up automatically. The item page gains a
  **battery panel** with the current level, predicted replacement date, fit
  confidence and a **depletion chart** of this and previous batteries, plus a
  **"Change battery"** button for manual swaps.
- **Battery tag.** Battery-tracked items are auto-assigned a system-managed
  **"Battery"** tag (re-added on the next reading if removed, protected from
  deletion). A household **Battery tag** preference lets you switch which tag
  is used, alongside the Box and Home Assistant tag pickers.
- **Battery API for Home Assistant.** The `/api/v1` API exposes battery
  tracking so HA can push levels: `GET /items/{item}/battery` (level, type,
  forecast, reminder), `POST /items/{item}/battery-readings` (push a level),
  `POST /items/{item}/battery-changes` (record a swap), and `battery_type` via
  `PATCH /items/{item}`. See [the API reference](./docs/api.md).

### Internal

- The depletion regression runs in a queued job (`RefreshBatteryForecast`) off
  the request path; recording a level stays a cheap insert and the forecast
  snapshot is cached on the open cycle. Adds `markrogoyski/math-php` (least
  squares) and `chart.js` + `vue-chartjs` (the depletion chart).

## [2026.06.06] — 2026-06-09

### Internal

- CI and the image build now run on **Node 24**. GitHub forces JS actions
  onto Node 24 from 2026-06-16 (Node 20 removed 2026-09-16), so every
  GitHub Action was bumped to its current major and the build toolchain
  (`node-version`, `node:24-alpine`, `@types/node`) moved to 24. Build/run
  only — no change to the running application.

## [2026.06.05] — 2026-06-09

### Added

- **The auth screens are now translated.** Login, register, password
  reset / forgot / confirm and the invalid-invite page were hardcoded
  English, so on a German install the pitch panel showed German while the
  form stayed English. Every auth UI string now goes through the
  translation layer (English + German).

### Changed

- **The mobile assistant button gets out of the way.** The floating
  assistant shortcut used to sit over the bottom-right of the page and
  cover content; it now slides away while you scroll down and returns when
  you scroll back up.

### Fixed

- **Item page action buttons were cut off on a narrow phone.** A long
  action label (notably the German "Unterelement hinzufügen") pushed the
  topbar action row — and the Contents / Related / Maintenance section
  toolbars — past the right edge instead of wrapping. Those rows now wrap
  onto a second line so every control stays reachable.
- **Dashboard "maintenance due" card clipped a long item name** on a
  narrow phone; it now truncates with an ellipsis.

### Internal

- Fixed three flaky/locale-dependent browser tests (mobile More-menu
  selector, login-error assertion, the seeded "Box" tag) so the browser
  suite is green again.

## [2026.06.04] — 2026-06-07

### Added

- **Assistant: maintenance & document tools.** The AI assistant gained a
  full set of maintenance abilities — `maintenance_overview` (what's
  overdue / due soon across the household), `create_maintenance_task`
  (interval or one-off reminders), `complete_maintenance_task`,
  `log_maintenance_entry` (ad-hoc repairs) — and now surfaces an item's
  related items, Paperless documents, Home Assistant device, maintenance
  schedules and recent history in `get_item`. Every write still asks for
  confirmation first. Fixed-calendar schedules stay in the web UI.
- **Link Paperless documents by hand.** A new "Link document" dialog on
  an item's Connections section attaches a Paperless-ngx document by id or
  pasted URL; admins can also full-text search Paperless from the dialog.
  Linking verifies the document exists and re-applies the Stockroom tag +
  backlink on the Paperless side (best-effort). The assistant can do the
  same via `link_paperless_document`, and read a linked document's text
  with `get_paperless_document` (linked documents only).
- **Suggest item fields from a linked document.** A ✨ action on each
  linked-document row re-reads the document's OCR text and proposes values
  for the item's fields — empty fields fill in, conflicts surface as
  explicit "Document says: X — apply?" choices, nothing is overwritten
  without a click.
- **Paperless document info on links.** Linked-document chips now show the
  document's title and type (cached locally) instead of a bare id, on the
  item page and in the assistant. A second "Refresh document info" button
  (and a daily background job) updates the cache after renames in
  Paperless, without writing anything back. `paperless:relink
  --metadata-only` exposes the same refresh on the CLI.
- **Home Assistant maintenance API.** The `/api/v1` API now exposes
  maintenance so Home Assistant can drive reminders: overdue / due-soon
  counters on `GET /statistics`, `GET /items/{item}/maintenance-tasks`,
  `POST /items/{item}/maintenance-tasks` (create) and
  `POST /maintenance-tasks/{task}/complete`. See [the API
  reference](./docs/api.md).

### Changed

- **Create / edit item forms are centred** instead of hugging the left
  edge on wide screens, and the duplicate "Find image" button on the
  form is gone — one trigger now lives in the image panel.

### Fixed

- **Hybrid (semantic) search could not be enabled on an existing
  install.** The entrypoint synced search settings against the previous
  boot's cached config (so a newly set `SCOUT_HYBRID_EMBEDDER` was
  invisible until a second boot), and Meilisearch refuses to register a
  `userProvided` embedder while the index still holds vector-less
  documents. The entrypoint now clears the stale cache on boot, and the
  "Rebuild search index" button flushes + re-syncs before reimporting —
  the supported way to switch hybrid search on. The full procedure is
  documented in `.env.docker.example`.
- **Cross-container state silently broke in the self-host stack.** With
  `CACHE_STORE=file` each container cached privately, so e.g. the reindex
  progress bar never moved. The cache now defaults to the database store,
  like sessions and the queue. Search indexing is also queued
  (`SCOUT_QUEUE=true`) so saving an item never blocks on an embedding
  round-trip.
- **Queue and scheduler containers always reported `unhealthy`.** They
  inherited the base image's Caddy-admin healthcheck, which only the web
  role can pass; they now use role-aware probes.
- **The assistant sometimes invented a `/maintenance/{id}` link.**
  Maintenance tasks have no page of their own — such links now resolve to
  the task's item page (or degrade to text), and the prompt was updated to
  prevent it.

### Internal

- A repo-wide Prettier pass cleared accumulated formatting drift, and a
  `pre-commit` hook now keeps staged frontend files formatted.

## [2026.06.03] — 2026-06-06

### Added

- **Maintenance tasks & reminders.** Items can now carry recurring
  maintenance schedules ("change smoke-detector batteries every 6 months",
  "service the heating every first Sunday in October") and a maintenance/
  repair history. Three schedule types: interval-after-completion (the next
  due date counts from the day you actually did it), fixed calendar rules
  (RFC 5545 RRULEs built from curated presets, evaluated via
  `simshaun/recurr`), and one-offs that archive themselves when done.
  Tasks live on the item page (create/edit/complete/skip/delete, plus
  ad-hoc history entries for repairs that never had a schedule), on a new
  household-wide `/maintenance` overview with overdue/due-soon filters and
  one-click completion, and on a dashboard card that appears only when
  something needs attention. A daily 07:00 digest email (per-user opt-in
  toggle in profile settings, rendered in each recipient's language) lists
  everything overdue or inside its per-task reminder window. All
  maintenance actions — including deletions — flow into the activity feed.
- **Invitation emails.** Entering an email address in the Members invite
  form now creates the invite AND mails it ("X invited you to Stockroom",
  rendered in the inviter's language, delivered via the queue worker),
  with a re-send button for pending invites and a sent-to indicator in
  the list. The classic copy-paste link flow is unchanged; an SMTP failure
  never loses the invite — the link stays copyable and the page says so.
  Emailed invites also prefill (but don't lock) the address on the
  registration form. Mail needs `MAIL_*` configured on the app, queue and
  scheduler services; `MAIL_TIMEOUT` (default 10 s) bounds SMTP waits.

## [2026.06.02] — 2026-06-04

### Fixed

- **Every `/api/v1/*` request returned 500 on existing deployments after
  upgrading to 2026.06.01.** `bootstrap/cache` is a persisted volume shared
  with the worker containers, so it shadows the package-discovery manifest
  baked into the image — and Laravel trusts an existing `packages.php`
  without ever staleness-checking it against `vendor/`. An image upgrade
  that adds a package (Sanctum, in this case) therefore never registered
  its service provider: the `sanctum` auth guard stayed undefined and every
  API route 500'd before auth even ran, while the web app kept working.
  The entrypoint now runs `php artisan package:discover` on every web boot,
  re-deriving the manifest from the image's `vendor/` so upgrades are
  immune to the stale volume. Existing broken installs heal on the next
  container restart with this image; the one-off manual fix is
  `docker compose exec app php artisan package:discover` + restart.

## [2026.06.01] — 2026-06-04

### Added

- **Home Assistant integration.** A token-authenticated REST API (`/api/v1`)
  lets the companion HACS integration
  [`ha-stockroom`](https://github.com/JeffreyDissmann/ha-stockroom) connect
  Home Assistant to your inventory: statistics, search, rooms/tags, and a
  strict 1:1 link between an HA device (or entity) and a Stockroom item. Each
  linked item carries a deep link back to its HA device page; HA can also
  create items for unmatched devices. Endpoints include a one-call
  `GET /api/v1/home-assistant-links` (every linked item with its link embedded)
  for the integration's Repair feature. See
  [`docs/home-assistant-integration.md`](./docs/home-assistant-integration.md)
  and the [API reference](./docs/api.md).
- **API tokens.** Manage personal access tokens under **Settings → API tokens**
  — name them, scope them `read` and/or `write`, copy once, revoke any time.
  Stateless Bearer auth, rate limited per token.
- **Auto-assigned `HomeAssistant` tag.** Linking an item tags it (and unlinking
  untags it) so you can filter everything tied to Home Assistant in one place.
  The tag is created on first link and selectable in **Household preferences**;
  once selected it's protected from deletion.
- **Connections card.** The item page groups external links — Paperless
  documents and the Home Assistant device — in one card (read-only on Show,
  unlink on Edit).
- **`home-assistant:adopt-custom-field` command.** Migrates manually-stored
  Home Assistant device URLs (or entity ids) from a custom field into proper
  links, idempotently and non-destructively, with `--dry-run`.

## [2026.05.09] — 2026-05-31

### Added

- **Bulk edit on the items index, search, and item Show.** Toggle Select
  mode and tap rows (or use `Cmd/Ctrl-A`) to multi-select; a sticky action
  bar offers Delete, Move, Add tag and Remove tag. Move flashes a 6-second
  Undo toast that reverses every item to its previous parent. Backend
  defers the slow part (Ollama embeddings + Meilisearch upsert) to a
  background `ReindexItemsJob`; the search index catches up on the queue worker
  a couple seconds later.
- **Installable PWA.** Manifest + icons (192 / 512 px, maskable-safe) +
  a service worker that precaches the app shell and stale-while-revalidates
  the last ~30 visited item pages and their images. Adds Stockroom to
  iOS / Android homescreens or Chromium's "Install app" with a proper
  standalone window. Service worker registers in production builds only.
- **Login context panel.** Pitch, "Made by", GitHub link, MIT license,
  and a `tag · commit` chip on `/login` / `/register` / `/forgot-password`.
  Build provenance flows from `--build-arg APP_VERSION` + `APP_COMMIT`
  in the release workflow → Dockerfile ENV → `config('stockroom.version.*')`
  → Inertia shared prop, so it survives `php artisan config:cache` in
  production. Dev environments fall back to a cached `git describe`.

### Changed

- **Project status** moved from alpha to beta. Data model has been stable
  across several releases, daily-driver workflows are all in place.
- **Topbar overflow** on item Show. Secondary actions (Create box, Delete)
  now fold into the existing `⋮ More` menu below `xl` (1280 px) instead
  of `md` (768 px), so a deeply-nested breadcrumb no longer pushes the
  action row past the right edge of a narrow desktop.
- **Breadcrumb truncation.** Chains > 4 entries collapse the middle into
  a `…` dropdown. The first crumb, the parent, and the current item are
  always visible; per-crumb ellipsis caps any single name at ~24ch so a
  long item name can't take over the row.
- **App-shell top nav.** Below `lg` (1024 px) the nav drops link labels
  and kbd shortcut hints, falling back to icon-only with a `title`
  tooltip. The "Stockroom" wordmark drops at the same breakpoint; the
  logo carries the brand. Fits down to ~700 px without wrapping.
- **Mobile assistant FAB** now only appears on Dashboard, Inventory
  (browse + item Show), and Search — not on the create / edit forms,
  Settings, Tags, Household, Activity, or while bulk Select mode is on.
  Reachable from the bottom-tabs "More" menu everywhere else.
- **Mobile list view** of items drops the Type / Tags / Inside columns
  to fit a phone screen. Tags surface inline as small pills under the
  item name; description softly clamps to two lines.
- **iOS standalone mode** safe area. The viewport meta gains
  `viewport-fit=cover` so `env(safe-area-inset-bottom)` returns a real
  value and the bottom tab bar lifts above the home indicator instead
  of sitting on the rounded corner.

### Removed

- **Find image** button on item Show — desktop topbar entry and the
  mobile `⋮ More` row. The action moves into the Edit form's image
  panel, where the user is already thinking about images. The same
  `?focus=images` deep-link from Create-Box is dropped; the dialog
  refs / orphaned `SearchImageDialog` import / unused `ImagePlus`
  icon all go with it.

## [2026.05.08] — 2026-05-30

### Added

- **Paperless-ngx integration (#7).** Tag a document with **Add to
  Stockroom** in Paperless and Stockroom extracts inventory items off
  the OCR text via the AI agent, creates them, and writes a back-link
  URL (`Stockroom URL` custom field) and the **Stockroom** tag onto
  the doc — single PATCH so the workflow doesn't re-fire on its own
  writes. Setup is one artisan command (`paperless:install`); the
  workflow definition self-heals if the webhook URL or shared secret
  later drifts. A **Repair Paperless links** button on household
  preferences walks every linked doc and re-applies the annotation
  (live progress bar, mirrors the search-index UX). Multi-item
  receipts produce one item per line; zero-extraction docs fall back
  to a single placeholder so every tagged doc produces _something_.
  Full walkthrough in
  [`docs/paperless-integration.md`](./docs/paperless-integration.md).
- **`paperless:adopt-custom-field` command.** Migrates pre-existing
  manual Paperless references (a custom field holding either a doc id
  or a URL like `https://paperless.host/documents/447/`) into proper
  `paperless_links` rows so the new UI surfaces them. `--relink` runs
  the repair job synchronously afterwards. Listing variant when no
  field name is supplied prints every custom field with its key, type
  and item count so the operator can pick the right one.
- **Auto-cover for Paperless intake.** When `BRAVE_SEARCH_KEY` is
  configured, every item created by the intake job runs a Brave image
  search and attaches the first hit as its primary image. Failure
  paths (no key, no results, download error) log and skip; intake is
  never blocked on the cover.
- **Household preference: Paperless intake destination.** Admin picks
  a room or container that newly extracted items land inside, instead
  of falling out at the top level. Scout-backed searchable picker
  so the list stays usable as the inventory grows. The selected item
  can't be deleted while it's the intake parent — same shape as the
  existing box-tag guard.
- **Search filter for Paperless docs.** `/search?paperless_document={id}`
  scopes results to items linked to that doc, with a removable chip
  in the header. This is the URL the back-link in Paperless points
  at — click it from any tagged doc and land on Stockroom's filtered
  view of the items extracted from it.

### Internal

- Per-instance memoization on `PaperlessClient` for tag-id and
  custom-field-id lookups; `annotateProcessed` resolves three names
  in one round-trip-cached batch instead of three separate GETs.
- Every Paperless UI surface gated by a `features.paperless` shared
  Inertia prop (defense-in-depth alongside the server-side
  `EnsurePaperlessEnabled` middleware): when `PAPERLESS_URL` or
  `PAPERLESS_TOKEN` is blank, the entire integration is invisible.

## [2026.05.07] — 2026-05-29

### Fixed

- **Queue OOM when HomeBox import processed large photos.** With
  2026.05.06 the import job actually ran for the first time on the
  NAS, but it died on the first ~6000×4000 phone photo with
  `Allowed memory size of 536870912 bytes exhausted` inside GD's
  image cloner. `ItemImageProcessor::writeVariants` had cloned the
  decoded source three times — once per variant
  (original/large/thumb) — and each clone duplicated the full GD
  pixel buffer (~200 MB for that resolution). Source + clone +
  transient resize buffer briefly coexisted, pushing peak memory
  past the 512 MB limit. Since the variants are emitted in
  monotonic-shrink order, the source can be mutated in place — no
  clones, peak memory drops from ~3× the decoded source to ~1×.
  The 2026.05.04 `memory_limit` bump to 512 MB stays as headroom,
  but the algorithm is now the durable fix and should handle phone
  photos up to ~50 MP comfortably.

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
  (suffixed only on the _named_ exports because `export`/`import` are
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

[Unreleased]: https://github.com/JeffreyDissmann/stockroom/compare/2026.06.06...HEAD
[2026.06.06]: https://github.com/JeffreyDissmann/stockroom/compare/2026.06.05...2026.06.06
[2026.06.05]: https://github.com/JeffreyDissmann/stockroom/compare/2026.06.04...2026.06.05
[2026.06.04]: https://github.com/JeffreyDissmann/stockroom/compare/2026.06.03...2026.06.04
[2026.06.03]: https://github.com/JeffreyDissmann/stockroom/compare/2026.06.02...2026.06.03
[2026.06.02]: https://github.com/JeffreyDissmann/stockroom/compare/2026.06.01...2026.06.02
[2026.06.01]: https://github.com/JeffreyDissmann/stockroom/compare/2026.05.09...2026.06.01
[2026.05.09]: https://github.com/JeffreyDissmann/stockroom/compare/2026.05.08...2026.05.09
[2026.05.08]: https://github.com/JeffreyDissmann/stockroom/compare/2026.05.07...2026.05.08
[2026.05.07]: https://github.com/JeffreyDissmann/stockroom/compare/2026.05.06...2026.05.07
[2026.05.06]: https://github.com/JeffreyDissmann/stockroom/compare/2026.05.05...2026.05.06
[2026.05.05]: https://github.com/JeffreyDissmann/stockroom/compare/2026.05.04...2026.05.05
[2026.05.04]: https://github.com/JeffreyDissmann/stockroom/compare/2026.05.03...2026.05.04
[2026.05.03]: https://github.com/JeffreyDissmann/stockroom/compare/2026.05.02...2026.05.03
[2026.05.02]: https://github.com/JeffreyDissmann/stockroom/compare/2026.05.01...2026.05.02
[2026.05.01]: https://github.com/JeffreyDissmann/stockroom/releases/tag/2026.05.01
