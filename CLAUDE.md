# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# PHP tests
composer test
composer test:dox
composer test:coverage
./vendor/bin/phpunit --filter ClassName

# JS build
npm run build          # production
npm run build:dev      # development
npm run watch          # watch mode

# PHP syntax check (run after editing PHP files)
php -l source/php/Path/To/File.php
```

## Architecture

WordPress plugin bootstrapped in `typesense-search.php` → `new App()`.

`App::__construct()` builds three shared services — `SettingsRepository`, `TypesenseClientService`, `IndexingLogLogger` — then passes them into six **Feature bootstrap classes** under `source/php/Bootstrap/`. Each feature's `register()` method wires its WordPress hooks. `App::getRegistry()` exposes the `IndexingRegistry` as a static accessor for CLI commands and external code.

### Key services

- **`SettingsRepository`** — the runtime source of truth for all plugin options. All runtime code that needs a setting gets it here. Settings views may read `get_option()` directly — that is intentional.
- **`Typesense/AdminApi`** — centralises all raw `wp_remote_*` calls to the Typesense HTTP API. Inject this; don't call `wp_remote_*` directly.
- **`Typesense/ClientFactory`** — builds a typed `\Typesense\Client` from settings; use `ClientFactory::fromSettings($settingsRepository)`.
- **`Typesense/ServerCapabilities`** — instance class, takes `AdminApi` via constructor; detects server version and feature support.

### Indexing

`IndexingRegistry` holds two strategy sets:

- **WordPress strategies** (`IndexingStrategyInterface`) — event-driven, wired to post lifecycle hooks, resolved per-post via `resolve(WP_Post)`. Register more specific strategies (e.g. PDF) before generic ones (e.g. Post).
- **External strategies** (`ExternalIndexingStrategyInterface`) — pull-driven, triggered by cron or CLI via `runExternalSync($id)` / `runAllExternalSyncs()`.

`IndexingFeature` builds and populates the registry. `IndexingHooks` connects WordPress save/delete events to the registry.

### Admin JS

Vite entry points (`vite.config.mjs`) compile to `assets/dist/`. Four JS bundles:

- `typesense-search.ts` — frontend search UI (modules under `source/js/typesense-search/`)
- `admin-settings.ts` — settings page (thin entry + section modules under `source/js/admin-settings/`)
- `pinned-results-admin.ts` — pinned results page (thin entry + modules under `source/js/pinned-results/`)
- `quick-search.ts` — frontend quick-search overlay (shares modules from `source/js/typesense-search/`)

Shared admin utilities live in `source/js/admin/` — import from there, don't copy. Named exports only; factory functions, not classes.

For new admin pages follow:
```
source/js/admin/             shared utilities
source/js/<page-name>/       page modules
source/js/<page-name>.ts     thin entry that calls init() on each module
```

### PHP namespace → directory mapping

```
TypesenseSearch\             source/php/
TypesenseSearch\Bootstrap\   source/php/Bootstrap/     (feature wiring)
TypesenseSearch\Admin\       source/php/Admin/
TypesenseSearch\Admin\Ajax\  source/php/Admin/Ajax/    (one class per AJAX action)
TypesenseSearch\Admin\Settings\ source/php/Admin/Settings/
TypesenseSearch\CLI\         source/php/CLI/
TypesenseSearch\CLI\Actions\ source/php/CLI/Actions/   (one class per WP-CLI sub-action)
TypesenseSearch\Indexing\    source/php/Indexing/
TypesenseSearch\PinnedResults\ source/php/PinnedResults/
TypesenseSearch\SearchStatistics\ source/php/SearchStatistics/
TypesenseSearch\Services\    source/php/Services/
TypesenseSearch\Typesense\   source/php/Typesense/
TypesenseSearch\Frontend\    source/php/Frontend/
```

### Tests

PHPUnit + Brain Monkey (WP function stubs) + Mockery. Base class `tests/TestCase.php` sets up Monkey and stubs common WP functions. Run against PHP 8.3+.

74 tests, 117 assertions (as of PR 7).

## Refactor principles

- Keep changes behavior-preserving unless explicitly requested.
- Prefer small, reviewable refactors over one huge rewrite.
- Keep WordPress integration points obvious: hooks, settings registration, AJAX, REST, cron, uninstall, and CLI should be easy to find.
- Avoid creating abstractions just to make the code look enterprise-shaped. Extract only where ownership is already unclear or duplication is real.
- Preserve public hooks, filters, option names, table names, CLI commands, and REST routes unless there is a migration plan.
- Verify after each step with at least `php -l` on changed PHP files and `npm run build`.

## Stable identifiers

Do not rename option names, table names, hooks, REST routes, AJAX action names, or CLI commands unless the user explicitly asks for a migration.
