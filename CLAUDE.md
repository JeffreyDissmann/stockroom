<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v2
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/scout (SCOUT) - v11
- tightenco/ziggy (ZIGGY) - v2
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/vue3 (INERTIA_VUE) - v2
- tailwindcss (TAILWINDCSS) - v3
- vue (VUE) - v3
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `vendor/bin/sail npm run build`, `vendor/bin/sail npm run dev`, or `vendor/bin/sail composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `vendor/bin/sail artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `vendor/bin/sail artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `vendor/bin/sail artisan make:test --pest SomeFeatureTest` instead of `vendor/bin/sail artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `vendor/bin/sail artisan test --compact` or filter: `vendor/bin/sail artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>

<project-testing-conventions>
# Browser testing (extends the Pest rules above)

The Boost "Pest" rules above cover unit/feature tests. This section adds the project's **browser testing** setup, which Boost does not describe.

- Some legacy tests under `tests/Feature` are still written as class-based PHPUnit `TestCase` classes. Pest runs them natively — do NOT convert them. Match the neighbouring file's style when adding tests.
- **Browser tests** live in `tests/Browser/`, use the Pest browser plugin (`pestphp/pest-plugin-browser`, Playwright-backed), and drive a real headless Chromium **inside the Sail container**. Write them as Pest functions using the global `visit()` helper.
  - Playwright is pinned to the exact version the plugin requires (currently `1.59.1` in `package.json`); a mismatch makes the plugin abort with "Playwright is outdated".
  - The chromium binary is installed to a **container-local path** (`/home/sail/pw-browsers`), NOT `node_modules`, because `node_modules` is bind-mounted from an iCloud-synced folder that evicts large files. Always run browser tests with that path exported:
    ```
    vendor/bin/sail bash -c "PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers ./vendor/bin/pest tests/Browser"
    ```
    If the binary is missing (container rebuilt, or eviction): `vendor/bin/sail bash -c "PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers npx playwright install chromium"`.
  - Rebuild frontend assets before running browser tests or they exercise stale UI: `vendor/bin/sail npm run build`.
  - Browser tests use `RefreshDatabase`, so the DB starts empty — create data with factories in each test; the demo seeder is NOT present.
  - Use `fill('#selector', 'value')` (not `type()`) for Vue `v-model` inputs so the value syncs to Inertia's `useForm` before submit.
  - Avoid asserting flows gated by native `confirm()` dialogs (item/image/tag delete) — headless Chromium auto-dismisses them; that logic is covered by the feature tests instead.
  - Use `data-test="..."` attributes for stable selectors; target them with Pest's `@name` selector syntax.

## Running tests

- Non-browser suite: `vendor/bin/sail artisan test --compact` (or `vendor/bin/sail bin pest --compact`).
- Browser suite: `vendor/bin/sail bash -c "PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers ./vendor/bin/pest tests/Browser"`.
- Run the minimal relevant tests while iterating; run the whole suite before declaring done.
</project-testing-conventions>

<project-routing-conventions>
# Routing in JS/TS — Wayfinder (no Ziggy, no hardcoded URLs)

This project uses **`laravel/wayfinder`** for typed route helpers in TS/Vue. **Ziggy has been removed** — do NOT add it back, do NOT call `route('name', args)`, and do NOT write URL strings like `'/items/' + id` or `<Link href="/tags">`. Generate and import a typed helper instead.

- **Generated tree** (committed) lives in `resources/js/{actions,routes,wayfinder}/`. It mirrors the Laravel route table 1:1 and is regenerated by the Vite plugin on dev + build, or manually with `vendor/bin/sail artisan wayfinder:generate`.
- **Import by route group**, e.g.:
  ```ts
  import { dashboard, search, login, logout, home, activity } from '@/routes';
  import itemRoutes from '@/routes/items';      // → itemRoutes.show(id), itemRoutes.create(), itemRoutes.images.store(id)
  import tagRoutes from '@/routes/tags';        // → tagRoutes.destroy(id)
  import profile from '@/routes/profile';       // → profile.edit().url
  ```
  Each helper returns `{ url, method }`. Call `.url` (or `.url(args)`) for an Inertia `Link :href`, `router.visit/post/patch/delete`, `useForm` `.post/.put/.patch/.delete`, or a `fetch(...)`.
- **Query params** go through the helper too: `search({ query: { q: term } }).url` — never compose `?q=…` by hand.
- **Naming clash** with a prop/ref (e.g. you also have a prop called `items` or `activity`) → alias the import: `import itemRoutes from '@/routes/items'` or `import { activity as activityRoute } from '@/routes'`.
- **Drift guard:** `vendor/bin/sail npm run wayfinder:check` regenerates and fails if `git diff` on the generated tree is non-empty. Run it in CI (or as a pre-commit hook) so a stale committed copy is caught before merge.
- **Prettier/ESLint** are configured to ignore the generated directories — don't reformat them.
</project-routing-conventions>
