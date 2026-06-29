# Typesense Search Refactor Roadmap

This document captures structural refactor ideas. It is intended for Michael,
Codex, or another AI agent coming back to improve the plugin later.

The main goal is maintainability. Do not treat this as a feature backlog and do
not change behavior unless a specific task asks for it.

## Item Index

Pending items are listed first. Completed items are at the bottom.

### Pending

| # | Title | Status |
|---|-------|--------|
| 14 | [Frontend config building could be separated](#14-frontend-config-building-could-be-separated) | ⏳ low urgency |
| 15 | [Future feature structure recommendation](#15-future-feature-structure-recommendation) | ⏳ guidance for when new features are added |
| 17 | [Frontend/I18n.php is unusually large](#17-frontendi18nphp-is-unusually-large) | ⏳ audit first |

### Skipped

| # | Title | Status |
|---|-------|--------|
| 11 | [Database lifecycle is similar but not shared](#11-database-lifecycle-is-similar-but-not-shared) | ⏭ skip — don't abstract two cases |

### Done

| # | Title | Status |
|---|-------|--------|
| 1 | [Uninstall cleanup naming and ownership](#1-uninstall-cleanup-naming-and-ownership) | ✅ PR 1 |
| 2 | [App bootstrap is becoming a hand-written container](#2-app-bootstrap-is-becoming-a-hand-written-container) | ✅ PR 3 |
| 3 | [Settings.php has too many jobs](#3-settingsphp-has-too-many-jobs) | ✅ PR 3 |
| 4 | [SettingsRepository promise is no longer true](#4-settingsrepository-promise-is-no-longer-true) | ✅ PR 4 |
| 5 | [Global function in advanced settings view](#5-global-function-in-advanced-settings-view) | ✅ PR 1 |
| 6 | [Inline JavaScript in PHP views](#6-inline-javascript-in-php-views) | ✅ PR 1 |
| 7 | [SettingsAjax is a large multi-purpose controller](#7-settingsajax-is-a-large-multi-purpose-controller) | ✅ PR 2 |
| 8 | [Typesense HTTP/admin access is scattered](#8-typesense-httpadmin-access-is-scattered) | ✅ PR 4 |
| 9 | [Pinned results sync-state policy lives in Repository](#9-pinned-results-sync-state-policy-lives-in-repository) | ✅ PR 5 |
| 10 | [Admin JavaScript files are becoming page apps](#10-admin-javascript-files-are-becoming-page-apps) | ✅ PR 7 |
| 12 | [Capability detection is static and option-based](#12-capability-detection-is-static-and-option-based) | ✅ PR 6 |
| 13 | [Source tree has an empty or stray directory](#13-source-tree-has-an-empty-or-stray-directory) | ✅ PR 1 |
| 16 | [CLI/IndexCommand is the largest file in the plugin](#16-cliindexcommand-is-the-largest-file-in-the-plugin) | ✅ PR 2 |

## Current Context

The plugin currently includes:

- Frontend Typesense search.
- Quick search overlay.
- Indexing for posts, pages, PDFs, Modularity content, and external strategies.
- Search statistics and local search log.
- Pinned search results backed by Typesense 30+ curation sets.
- Admin settings, admin AJAX endpoints, REST endpoints, CLI commands, and
  uninstall cleanup.

## Refactor Principles

- Keep changes behavior-preserving unless explicitly requested.
- Prefer small, reviewable refactors over one huge rewrite.
- Keep WordPress integration points obvious: hooks, settings registration,
  AJAX, REST, cron, uninstall, and CLI should be easy to find.
- Avoid creating abstractions just to make the code look enterprise-shaped.
  Extract only where ownership is already unclear or duplication is real.
- Preserve public hooks, filters, option names, table names, CLI commands, and
  REST routes unless there is a migration plan.
- Verify after each step with at least PHP syntax checks and `npm run build`.

## Recommended Order

Work in PR-sized batches. Each batch should leave the plugin fully functional.

**PR 0 — Test infrastructure only (done)**

Added PHPUnit, Brain Monkey, Mockery, `phpunit.xml.dist`, `tests/bootstrap.php`,
a base test case, Composer test scripts, and a GitHub Actions workflow.
Initial characterization tests for sanitizers, `SettingsRepository`,
`DocumentBuilder`, pinned-result delete behavior, and database migration guards.

**PR 1 — Pure cleanup, zero behavioral risk (items 1, 5, 6, 13) (done)**

Renamed uninstall helper. Extracted search-result behavior card to a partial.
Moved inline JavaScript into `admin-settings.js`. Removed empty `AcfFields/`
directory. Softened `SettingsRepository` docblock.

**PR 2 — Large-file splits (items 7, 16) (done)**

`SettingsAjax.php` (36 K) split into 6 action classes under
`source/php/Admin/Ajax/` plus a shared `AjaxHelpers` trait.
`IndexCommand.php` (63 K) split into 5 action classes under
`source/php/CLI/Actions/`. 32 new tests.

**PR 3 — Bootstrap and settings restructure (items 2, 3) (done)**

`App.php` split into 6 feature bootstrap classes under `source/php/Bootstrap/`.
`Settings.php` split into `OptionKeys`, `Sanitizers`, `SettingsPage`,
`SettingsRegistry` under `source/php/Admin/Settings/`. `Settings extends
OptionKeys` so all `Settings::OPTION_*` call sites are unchanged.
60 tests total, 96 assertions.

**PR 4 — Option access and Typesense API (items 4, 8) (done)**

Created `Typesense/AdminApi.php` — centralises all raw `wp_remote_*` calls.
`ClientFactory::fromSettings(SettingsRepository)` added for injected callers.
All Ajax action classes, `MetaBox`, `EnrichSearchTemplate`,
`PinnedResults\Repository`, and indexing actions receive `SettingsRepository`
via constructor. 67 tests total, 107 assertions.

**PR 5 — Pinned results sync-state fix (item 9) (done)**

`Repository::delete()` now fetches the row first and only calls
`markAllPending()` when the deleted rule had a non-null `synced_at`.
Never-synced rules no longer trigger a full re-sync.
71 tests total, 110 assertions.

**PR 6 — ServerCapabilities instance service (item 12) (done)**

`ServerCapabilities` converted from a static class to an instance class with
`AdminApi` injected via constructor. Per-instance `?string $cached` replaces
the process-global `static $cached`. `Collection::getSchema()` uses
`SettingsRepository::isPinnedResultsEnabled()`, eliminating the last direct
option read in a non-view runtime class. `RestController` and
`PinnedResultsPage` now receive `ServerCapabilities` via constructor.
74 tests total, 117 assertions.

**PR 7 — Admin JS modularization (item 10) (done)**

Extracted shared admin utilities to `source/js/admin/`: `ajax.ts`,
`button.ts`, `dom.ts`, `debounce.ts`, `toast.ts`, `sortable-list.ts`,
`autocomplete.ts`. `admin-settings.js` (1145 lines, plain JS) converted to
TypeScript and split into a thin entry plus six section modules under
`source/js/admin-settings/`. `pinned-results-admin.ts` (647 lines) split into
`source/js/pinned-results/`: `types.ts`, `state.ts`, `api.ts`, `render.ts`,
`events.ts`. Dep graph: `state ← render ← api ← events ← entry`.
Vite entry points unchanged; build output is identical.

**Remaining / as needed (items 14, 15, 17)**

- Frontend config split (item 14) — low urgency.
- Future feature structure (item 15) — guidance, not a code task.
- `I18n.php` audit (item 17) — audit first, refactor only if it is doing too
  much.

**Skip for now (item 11)**

`PluginTable` interface — not worth adding for two tables.

---

## Done Items

### 1. Uninstall Cleanup Naming And Ownership

Done in PR 1. Renamed to `typesense_search_uninstall_data_for_site()`.

### 2. App Bootstrap Is Becoming A Hand-Written Container

Done in PR 3. Split into feature bootstrap classes under
`source/php/Bootstrap/`. `App::getRegistry()` unchanged.

### 3. Settings.php Has Too Many Jobs

Done in PR 3. Split into `OptionKeys`, `Sanitizers` (trait), `SettingsPage`,
`SettingsRegistry` under `source/php/Admin/Settings/`. `Settings extends
OptionKeys` so all `Settings::OPTION_*` call sites are unchanged.

### 4. SettingsRepository Promise Is No Longer True

Done in PR 4. All plugin-owned option reads in non-view runtime classes now
route through `SettingsRepository`. Settings views may still use `get_option()`
directly — that is intentional.

### 5. Global Function In Advanced Settings View

Done in PR 1. Extracted to
`views/admin/settings-tabs/advanced/search-result-behavior.php`.

### 6. Inline JavaScript In PHP Views

Done in PR 1. Moved into `admin-settings.js` (now `admin-settings.ts`
after PR 7).

### 7. SettingsAjax Is A Large Multi-Purpose Controller

Done in PR 2. Split into 6 action classes under `source/php/Admin/Ajax/`
plus a shared `AjaxHelpers` trait. Existing AJAX action names unchanged.

### 8. Typesense HTTP/Admin Access Is Scattered

Done in PR 4. Centralised in `source/php/Typesense/AdminApi.php`.

### 9. Pinned Results Sync-State Policy Lives In Repository

Done in PR 5. `Repository::delete()` only calls `markAllPending()` when the
deleted rule had a non-null `synced_at`.

### 10. Admin JavaScript Files Are Becoming Page Apps

Done in PR 7. New admin pages should follow this pattern:

```text
source/js/admin/           shared utilities — import from here, don't copy
source/js/<page-name>/     modules for a specific admin page
source/js/<page-name>.ts   thin entry point that calls init() on each module
```

`quick-search.ts` was left as-is — it is a frontend widget, not an admin page.

### 11. Database Lifecycle Is Similar But Not Shared

Skipped. Two tables with matching static method signatures is not a smell worth
fixing. Add a `PluginTable` interface only if a third feature table is
introduced.

### 12. Capability Detection Is Static And Option-Based

Done in PR 6. `ServerCapabilities` is now an instance class with `AdminApi`
injected via constructor.

### 13. Source Tree Has An Empty Or Stray Directory

Done in PR 1. Removed empty `source/php/AcfFields/` directory.

### 16. CLI/IndexCommand Is The Largest File In The Plugin

Done in PR 2. Split into 5 action classes under `source/php/CLI/Actions/`.
`IndexCommand.php` is now a thin delegator.

---

## Pending Items

### 14. Frontend Config Building Could Be Separated

`Frontend/TypesenseConfig.php` both decides whether config is needed, builds
frontend and quick search config, maps Web Awesome locales, reads facets,
applies filters, and injects inline scripts.

Suggested split:

```text
Frontend/TypesenseConfigBuilder.php
Frontend/TypesenseConfigLocalizer.php
```

Low urgency — the file is large but self-contained.

### 15. Future Feature Structure Recommendation

If adding search shortcuts, notices, or query suggestions, avoid putting
everything into the existing settings page.

For interactive or list-heavy features, use a dedicated admin page:

```text
source/php/<FeatureName>/
    Database.php
    Repository.php
    RestController.php
    AdminPage.php

source/js/<feature-name>-admin/
    types.ts
    state.ts
    api.ts
    render.ts
    events.ts
source/js/<feature-name>-admin.ts
```

The main settings page should stay for configuration toggles, not rule
management.

### 17. Frontend/I18n.php Is Unusually Large

`source/php/Frontend/I18n.php` is approximately 10 K. Audit before touching.

Possible causes: large inline locale map, Web Awesome locale mapping, JS
localization payload injection that belongs in `TypesenseConfig`.

If the file is doing multiple jobs, extract:

```text
Frontend/I18n.php              plugin string registration only
Frontend/WebAwesomeLocale.php  locale detection and mapping
```

There is already a `source/js/webawesome-locale.ts` — check whether the PHP
file is duplicating that logic server-side before splitting.

---

## Testing Strategy

Tests pin current behavior before refactoring and give ongoing confidence when
new features are added.

### Current Setup

- `phpunit.xml.dist`, `tests/bootstrap.php`, `tests/TestCase.php`,
  `tests/Unit/`
- Composer scripts: `composer test`, `composer test:dox`,
  `composer test:coverage`
- GitHub Actions: `.github/workflows/test.yml` (PHP 8.3 and PHP 8.4)
- Stack: PHPUnit + Brain Monkey (WP function stubs) + Mockery (object mocks)
- PHP 8.3+ required
- Current count: **74 tests, 117 assertions**

### What To Test

For new features, write tests first. Target:

- Repository methods: save, fetch, delete, sync-state transitions.
- REST controller: authorization, response shape, error cases.
- Sanitizers and validators for new settings.

When a refactor touches a class, write characterization tests against the
current code first so they catch accidental behavior changes.

### What Not To Test

- **Views and rendered HTML** — brittle.
- **Full AJAX handler integration** — test the logic inside the handler, not
  the WordPress request cycle.
- **Full indexing/rebuild flow against a real Typesense server** — belongs in
  an end-to-end suite, out of scope here.
- **WordPress core behavior** — trust `add_action`, `get_option`, etc.

### Running Tests

```bash
composer test
composer test:dox
composer test:coverage
./vendor/bin/phpunit --filter SettingsRepository
```

---

## Notes For AI Agents

- Read the relevant files before editing. Do not refactor from this document
  alone.
- Avoid broad formatting churn.
- Keep option names, table names, hooks, REST routes, AJAX action names, and
  CLI commands stable unless the user explicitly asks for migrations.
- Run `php -l` on changed PHP files and `npm run build` after JS changes.
- `SettingsRepository` is the runtime source of truth for plugin-owned options.
  Settings views may use `get_option()` directly — that is intentional.
- For new JS-heavy admin pages, follow the `source/js/admin/` +
  `source/js/<page>/` pattern from PR 7. Named exports only; factory functions,
  not classes.
- The PR ordering in Recommended Order matters for understanding dependencies.
  PRs 0–7 are done; start from the Pending section.
