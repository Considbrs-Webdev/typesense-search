# Typesense Search Refactor Roadmap

This document captures structural refactor ideas for a future branch. It is
intended for Michael, Codex, or another AI agent coming back to improve the
plugin later.

The main goal is maintainability. Do not treat this as a feature backlog and do
not change behavior unless a specific task asks for it. The plugin has grown a
lot through incremental feature work, and the code is still workable, but some
boundaries are now blurry.

## Item Index

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
| 9 | [Pinned results sync-state policy lives in Repository](#9-pinned-results-sync-state-policy-lives-in-repository) | ⏳ behavior fix — test written, implementation pending |
| 10 | [Admin JavaScript files are becoming page apps](#10-admin-javascript-files-are-becoming-page-apps) | ⏳ do before adding new JS-heavy admin pages |
| 11 | [Database lifecycle is similar but not shared](#11-database-lifecycle-is-similar-but-not-shared) | ⏭ skip — don't abstract two cases |
| 12 | [Capability detection is static and option-based](#12-capability-detection-is-static-and-option-based) | ⏳ unblocked now that AdminApi exists |
| 13 | [Source tree has an empty or stray directory](#13-source-tree-has-an-empty-or-stray-directory) | ✅ PR 1 |
| 14 | [Frontend config building could be separated](#14-frontend-config-building-could-be-separated) | ⏳ low urgency |
| 15 | [Future feature structure recommendation](#15-future-feature-structure-recommendation) | ⏳ guidance for when new features are added |
| 16 | [CLI/IndexCommand is the largest file in the plugin](#16-cliindexcommand-is-the-largest-file-in-the-plugin) | ✅ PR 2 |
| 17 | [Frontend/I18n.php is unusually large](#17-frontendi18nphp-is-unusually-large) | ⏳ audit first |

## Current Context

The plugin currently includes:

- Frontend Typesense search.
- Quick search overlay.
- Indexing for posts, pages, PDFs, Modularity content, and external strategies.
- Search statistics and local search log.
- Pinned search results backed by Typesense 30+ curation sets.
- Admin settings, admin AJAX endpoints, REST endpoints, CLI commands, and
  uninstall cleanup.

The biggest structural issue is not that any one component is broken. It is that
new features have mostly been added beside the old structure instead of giving
the plugin clearer feature-level boundaries.

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

- Added PHPUnit, Brain Monkey, Mockery, `phpunit.xml.dist`,
  `tests/bootstrap.php`, a base test case, Composer test scripts, and a
  GitHub Actions workflow.
- Added initial characterization tests for settings sanitizers,
  `SettingsRepository`, `DocumentBuilder`, pinned-result delete behavior, and
  database migration guards.
- The suite currently has one intentionally incomplete test documenting the
  desired future pinned-results sync-state fix from item 9.

**PR 1 — Pure cleanup, zero behavioral risk (items 1, 5, 6, 13) (done)**

- Renamed uninstall helper to `typesense_search_uninstall_data_for_site()`.
- Extracted search-result behavior card to `views/admin/settings-tabs/advanced/search-result-behavior.php`.
- Moved inline JavaScript into `source/js/admin-settings.js`.
- Removed empty `source/php/AcfFields/` directory.
- Softened `SettingsRepository` docblock.

**PR 2 — Large-file splits (items 7, 16) (done)**

- `SettingsAjax.php` (36 K) split into 6 action classes under `source/php/Admin/Ajax/`
  plus a shared `AjaxHelpers` trait. `SettingsAjax.php` is now a thin wiring class
  that holds the AJAX action name constants.
- `IndexCommand.php` (63 K) split into 5 action classes under `source/php/CLI/Actions/`.
  `IndexCommand.php` is now a thin delegator.
- Added characterization tests: `AjaxHelpersTest` (9 tests), `IndexCommandTest` (23 tests).

**PR 3 — Bootstrap and settings restructure (items 2, 3) (done)**

- `App.php` split into 6 feature bootstrap classes under `source/php/Bootstrap/`.
  `App::getRegistry()` unchanged.
- `Settings.php` split into `OptionKeys`, `Sanitizers` (trait), `SettingsPage`,
  `SettingsRegistry` under `source/php/Admin/Settings/`. `Settings extends OptionKeys`
  so all `Settings::OPTION_*` call sites are unchanged.
- Static helpers (`getIndexablePostTypes`, `isPdfToTextAvailable`, `isModularityAvailable`)
  moved to `SettingsRepository` with delegate stubs kept on `Settings`.
- 60 tests total, 96 assertions, 1 intentionally incomplete.

**PR 4 — Option access and Typesense API (items 4, 8) (done)**

- Created `Typesense/AdminApi.php` — centralises all raw `wp_remote_*`
  calls (previously split across `TypesenseSync::request()` and
  `ServerCapabilities::getServerVersion()`).
- `ServerCapabilities::getServerVersion()` delegates to `AdminApi`; static
  cache behaviour preserved for existing callers.
- `ClientFactory::fromSettings(SettingsRepository)` added for injected callers.
- All 5 Ajax action classes, `SettingsAjax`, `MetaBox`, `EnrichSearchTemplate`,
  `PinnedResults\Repository`, `PdfIndexingStrategy`, `ClearAction`, and
  `RebuildAction` receive `SettingsRepository` via constructor.
- `Settings::isPostTypeEnabled()` updated to delegate to `SettingsRepository`.
- Bootstrap classes and `IndexCommand` updated to wire the new dependencies.
- 67 tests total, 107 assertions, 1 intentionally incomplete.

**Later / as needed (items 9, 10, 12, 14, 15, 17)**

- Pinned results sync-state policy (item 9) — touches behavior, keep separate.
- Admin JS modularization (item 10) — do before adding new JS-heavy pages.
- `ServerCapabilities` instance service (item 12) — defer until `AdminApi`
  exists.
- Frontend config split (item 14) — low urgency.
- `I18n.php` audit (item 17) — audit first, refactor only if it is doing too
  much.

**Skip for now (item 11)**

- `PluginTable` interface — not worth adding for two tables.

## 1. Uninstall Cleanup Naming And Ownership

### Current smell

`uninstall.php` defines:

```php
typesense_search_uninstall_statistics_for_site()
```

but the function now deletes both search statistics data and pinned results
data.

Relevant file:

- `uninstall.php`

Current responsibilities:

- Drops `{$wpdb->prefix}typesense_search_events`.
- Drops `{$wpdb->prefix}typesense_pinned_results`.
- Deletes statistics options.
- Deletes pinned-results options.
- Clears the statistics retention cron hook.
- Handles multisite by switching blogs.

### Suggested refactor

Rename the helper to something more neutral:

```php
typesense_search_uninstall_data_for_site()
```

Then consider using feature-owned cleanup methods instead of duplicating table
names:

```php
\TypesenseSearch\SearchStatistics\Database::drop();
\TypesenseSearch\PinnedResults\Database::drop();
```

Because `uninstall.php` is intentionally self-contained, this needs a deliberate
choice:

- Either keep it self-contained but rename the helper.
- Or bootstrap just enough plugin code to call module cleanup methods.

The low-risk first step is only the rename.

## 2. App Bootstrap Is Becoming A Hand-Written Container

### Current smell

`source/php/App.php` instantiates almost everything directly:

- constants loader
- templates
- ACF fields
- admin settings
- admin AJAX
- meta boxes
- pinned results page
- frontend assets/config
- search statistics database, repository, REST controller, retention, widgets,
  admin actions, admin page
- pinned results database, repository, REST controller, sync service
- indexing registry and strategies
- indexing hooks
- enrichers
- CLI command

Relevant file:

- `source/php/App.php`

This is still readable, but it will keep growing as new feature areas are added.

### Suggested refactor

Introduce small feature bootstrap classes. For example:

```text
source/php/Bootstrap/AdminFeature.php
source/php/Bootstrap/FrontendFeature.php
source/php/Bootstrap/IndexingFeature.php
source/php/Bootstrap/SearchStatisticsFeature.php
source/php/Bootstrap/PinnedResultsFeature.php
source/php/Bootstrap/CliFeature.php
```

Each feature class should do only wiring. Avoid moving business logic into
bootstrap classes.

Possible target shape:

```php
$settings = new SettingsRepository();
$clientService = new TypesenseClientService($settings);
$logger = new IndexingLogLogger(new ErrorLogLogger());

(new AdminFeature($settings))->register();
(new FrontendFeature($settings))->register();
$searchStatistics = (new SearchStatisticsFeature($settings))->register();
(new PinnedResultsFeature($settings))->register();
$registry = (new IndexingFeature($settings, $clientService, $logger))->register();
(new CliFeature($settings, $searchStatistics, $registry))->register();
```

Keep `App::getRegistry()` working if external code may depend on it.

## 3. Settings.php Has Too Many Jobs

### Current smell

`source/php/Admin/Settings.php` owns:

- option key constants
- option group constants
- tab labels
- settings page registration
- all settings registration
- all settings sanitizers
- admin asset enqueueing
- render context for every settings tab
- static helpers for post types, weights, PDF availability, Modularity checks

This makes every new setting feel like it belongs in the same class forever.

### Suggested refactor

Split by responsibility:

```text
source/php/Admin/Settings/OptionKeys.php
source/php/Admin/Settings/SettingsRegistry.php
source/php/Admin/Settings/SettingsPage.php
source/php/Admin/Settings/Sanitizers.php
source/php/Admin/Settings/TabContext.php
```

This can be done gradually. A lower-risk first step:

1. Extract settings registration to `SettingsRegistry`.
2. Keep constants in `Settings` for backward compatibility.
3. Delegate from `Settings::registerSettings()` to the new class.

Avoid changing option names.

### Static helpers also belong elsewhere

`Settings.php` contains several static helpers that have nothing to do with
settings registration:

- `getIndexablePostTypes()` — belongs in `SettingsRepository` or a new
  `Environment` helper.
- `isPdfToTextAvailable()` — environment check, same as above.
- `isModularityAvailable()` — plugin detection, same as above.
- `isPostTypeEnabled()` — already proxied through `SettingsRepository`; the
  duplicate in `Settings` can be removed once routing is complete (see item 4).

Extract these when splitting `Settings.php` so they do not end up in
`SettingsPage` or `SettingsRegistry` by accident.

## 4. SettingsRepository Promise Is No Longer True

### Current smell

`source/php/Services/SettingsRepository.php` says plugin `get_option()` calls are
centralized there, but many direct `get_option()` calls remain in:

- settings views
- `Settings.php`
- `SettingsAjax.php`
- `Typesense/ClientFactory.php`
- `Typesense/ServerCapabilities.php`
- `Typesense/Collection.php`
- `Frontend/Assets.php`
- `Frontend/EnrichSearchTemplate.php`
- CLI code
- indexing strategies

Some direct option calls are fine for WordPress core options such as date/time
formats. The mismatch is mainly for plugin-owned options.

### Suggested refactor

Decide what the repository is meant to be:

- If it is the source of truth, route plugin option reads through it.
- If not, soften the docblock so it does not claim all option access is
  centralized.

Recommended direction: use `SettingsRepository` for runtime/plugin behavior,
but allow settings views to use raw `get_option()` when rendering forms.

Possible improvement:

- Add getters for every plugin option.
- Add grouped DTO-style methods for frontend config, quick search config, and
  search statistics config.
- Inject `SettingsRepository` into classes that currently call plugin options
  directly.

## 5. Global Function In Advanced Settings View

### Current smell

`views/admin/settings-tabs/advanced-settings.php` defines:

```php
typesense_search_render_search_result_behavior()
```

inside the template.

This works, but global functions in views are awkward because:

- they can be redeclared if the template is included twice in a request
- they are not namespaced
- they make view rendering harder to scan
- they hide a reusable partial inside another file

### Suggested refactor

Move the markup to a partial:

```text
views/admin/settings-tabs/advanced/search-result-behavior.php
```

Then include it from `advanced-settings.php`, passing required variables in
scope:

```php
include TYPESENSESEARCH_PATH . 'views/admin/settings-tabs/advanced/search-result-behavior.php';
```

If more advanced-settings cards are extracted, use a consistent folder:

```text
views/admin/settings-tabs/advanced/facets.php
views/admin/settings-tabs/advanced/search-field-weights.php
views/admin/settings-tabs/advanced/search-result-behavior.php
views/admin/settings-tabs/advanced/search-statistics.php
```

## 6. Inline JavaScript In PHP Views

### Current smell

`views/admin/settings-tabs/advanced-settings.php` contains inline JavaScript for:

- truncator mode
- debounce delay visibility
- statistics consent integration visibility

There is already `source/js/admin-settings.js`, so the behavior is split across
two places.

### Suggested refactor

Move the inline script into `source/js/admin-settings.js`.

Use stable data attributes instead of relying only on IDs where useful:

```html
data-js-truncator-mode
data-js-truncator-hidden
data-js-truncator-custom
data-js-debounce-toggle
data-js-debounce-delay-field
```

This keeps all admin behavior in the asset pipeline and makes it easier to test
and refactor later.

## 7. SettingsAjax Is A Large Multi-Purpose Controller

### Current smell

`source/php/Admin/SettingsAjax.php` is approximately 36 K and handles many
unrelated admin-ajax actions:

- connection test
- collection creation
- search key generation
- collection statistics
- clear post type
- reindex post type
- facet field discovery
- status check
- search key repair
- status tab collection creation
- indexing log clear

This class is doing too much.

### Suggested refactor

Split by responsibility:

```text
source/php/Admin/Ajax/ConnectionActions.php
source/php/Admin/Ajax/CollectionActions.php
source/php/Admin/Ajax/SearchKeyActions.php
source/php/Admin/Ajax/IndexingActions.php
source/php/Admin/Ajax/FacetActions.php
source/php/Admin/Ajax/LogActions.php
```

Alternative: move newer admin APIs to REST controllers, like pinned results
already does.

Keep existing AJAX action names stable unless intentionally migrating.

### Coupling note

Several handlers in `SettingsAjax.php` instantiate `ClientFactory` directly.
Once item 8 (`AdminApi`) exists, inject it into each action class instead of
calling `ClientFactory` or `wp_remote_*` inline. Do PR 2 (this split) before
PR 4 (the `AdminApi` wrapper) so that injection targets are smaller and
clearer.

## 8. Typesense HTTP/Admin Access Is Scattered

### Current smell

Several classes know how to speak to Typesense directly:

- `Typesense/ClientFactory.php`
- `Typesense/ApiKey.php`
- `Typesense/Collection.php`
- `Typesense/ServerCapabilities.php`
- `PinnedResults/TypesenseSync.php`
- `Admin/SettingsAjax.php`
- `CLI/IndexCommand.php`

Some use the Typesense PHP client. Some use `wp_remote_get()` or
`wp_remote_request()`.

### Suggested refactor

Introduce a small admin API wrapper, for example:

```text
source/php/Typesense/AdminApi.php
```

Possible responsibilities:

- `getServerVersion()`
- `getCollection()`
- `collectionExists()`
- `createCollection()`
- `patchCollection()`
- `putCurationSet()`
- `getStats()`
- shared request handling and error formatting

Do not overbuild this. Start by centralizing repeated raw HTTP request code and
server capability checks.

## 9. Pinned Results Sync-State Policy Lives In Repository

### Current smell

`PinnedResults\Repository::delete()` currently deletes a rule and then marks all
remaining rules pending.

That is structurally odd because the repository is both persistence and sync
policy.

It also causes an edge case: if all rules are synced, then an admin creates a
new unsynced rule and deletes that same unsynced rule before syncing, all other
rules become pending even though Typesense did not need a change.

### Suggested refactor

At minimum, update the delete policy:

- If the deleted rule had previously been synced, mark remaining rules pending.
- If it was never synced, delete it without changing the rest.

Better structure:

```text
PinnedResults\Repository       persistence only
PinnedResults\SyncStateService sync-state transitions
PinnedResults\TypesenseSync    remote sync
```

Keep this behavior change separate from broad structural refactors if possible,
because it is not purely structural.

## 10. Admin JavaScript Files Are Becoming Page Apps

### Current smell

Large single JS/TS files:

- `source/js/admin-settings.js` is over 1000 lines.
- `source/js/pinned-results-admin.ts` is a full mutable-state app in one file.
- `source/js/quick-search.ts` is also large.

This is acceptable for now, but future features like search shortcuts, search
notices, and "Did you mean" suggestions will likely need similar admin UIs.

### Suggested refactor

Create small shared admin utilities before adding more JS-heavy admin pages:

```text
source/js/admin/dom.ts
source/js/admin/rest.ts
source/js/admin/toast.ts
source/js/admin/debounce.ts
source/js/admin/autocomplete.ts
source/js/admin/sortable-list.ts
```

Then split pinned results into modules:

```text
source/js/pinned-results/state.ts
source/js/pinned-results/api.ts
source/js/pinned-results/render.ts
source/js/pinned-results/events.ts
source/js/pinned-results/types.ts
```

Do not introduce a framework unless there is a stronger reason. The current
plain TypeScript approach is fine if it is modular.

## 11. Database Lifecycle Is Similar But Not Shared

### Current smell

Search statistics and pinned results each have:

- `Database::tableName()`
- `Database::maybeMigrate()`
- `Database::migrate()`
- `Database::drop()`
- `OPTION_DB_VERSION`
- `DB_VERSION`

This duplication is not terrible, but lifecycle wiring is spread across
`App.php` and `uninstall.php`.

### Recommendation

Skip this for now. Two tables with matching static method signatures is not a
smell that needs fixing. Add the `PluginTable` interface only if a third feature
table is introduced, or if lifecycle code is already being touched for another
reason. Do not create an abstraction for two cases.

## 12. Capability Detection Is Static And Option-Based

### Current smell

`Typesense/ServerCapabilities.php` is static and reads options directly. It
caches the server version in a request-local static variable.

That is simple, but it makes dependency injection and tests harder. It also
means code that already has `SettingsRepository` still bypasses it.

### Recommendation

Defer this until item 8 (`AdminApi`) exists. Once `AdminApi` is in place,
converting `ServerCapabilities` to an instance service that accepts it as a
constructor argument is straightforward. Doing it before `AdminApi` exists just
moves the same static option reads into a constructor without a real benefit.

Target shape when the time comes:

```php
new ServerCapabilities($settings, $adminApi)
```

Keep a static facade only if external code calls `ServerCapabilities`
statically and cannot be updated at the same time.

## 13. Source Tree Has An Empty Or Stray Directory

There is a `source/php/AcfFields` directory alongside `source/php/ACF`.

The `AcfFields/` directory is confirmed empty. The active ACF integration lives
in `source/php/ACF/Fields.php`.

Suggested action:

- Remove `source/php/AcfFields/` entirely.
- No code changes needed; this is a filesystem cleanup only.

This is safe to do in PR 1.

## 14. Frontend Config Building Could Be Separated

### Current smell

`Frontend/TypesenseConfig.php` both:

- decides whether config is needed
- builds frontend search config
- builds quick search config
- maps Web Awesome locales
- reads facets
- applies template and placeholder filters
- injects inline scripts

### Suggested refactor

Split config assembly from script injection:

```text
Frontend/TypesenseConfigBuilder.php
Frontend/TypesenseConfigLocalizer.php
```

This would also make it easier to add future frontend config for search
shortcuts, search notices, or query suggestions.

## 15. Future Feature Structure Recommendation

If adding search shortcuts, notices, or query suggestions later, avoid putting
everything into the existing settings page.

Recommended shape:

```text
source/php/SearchRules/
    Database.php
    Repository.php
    RestController.php
    AdminPage.php

source/js/search-rules-admin/
    ...
```

Or split by feature:

```text
source/php/SearchShortcuts/
source/php/SearchNotices/
source/php/SearchSuggestions/
```

Use a dedicated admin page if the UI is interactive or list-heavy. The main
settings page should stay for configuration toggles, not rule management.

## 16. CLI/IndexCommand Is The Largest File In The Plugin

### Current smell

`source/php/CLI/IndexCommand.php` is approximately 63 K — the largest single
file in the entire plugin. It almost certainly mixes command parsing, business
logic, progress output, and error handling in one place.

This is a higher practical risk than most other items on this list. WP-CLI
commands are harder to test manually than admin pages, and breakage is
invisible to users until they run `wp typesense`.

Relevant file:

- `source/php/CLI/IndexCommand.php`

### Suggested refactor

Split by subcommand or action:

```text
source/php/CLI/IndexCommand.php    (entry point, registers subcommands)
source/php/CLI/Actions/IndexAction.php
source/php/CLI/Actions/RebuildAction.php
source/php/CLI/Actions/ClearAction.php
source/php/CLI/Actions/StatusAction.php
source/php/CLI/Actions/RepairAction.php
```

Each action class receives its dependencies via constructor (registry,
settings, etc.) and is responsible for one logical operation. `IndexCommand`
becomes thin: it reads CLI arguments, validates them, and delegates.

Read the file before splitting. The subcommand boundary may not match the
method names exactly — let the actual code drive the split, not this document.

### Verification

```bash
php -l source/php/CLI/IndexCommand.php
wp typesense --help          # confirm commands still register
wp typesense index --dry-run
wp typesense rebuild --dry-run --skip-index
```

## 17. Frontend/I18n.php Is Unusually Large

### Current smell

`source/php/Frontend/I18n.php` is approximately 10 K. For a localization file
this is large and worth auditing.

Possible causes:

- It contains a large string map inline instead of delegating to `.pot`/`.po`
  files.
- It handles locale mapping for Web Awesome (icon library) in addition to
  plugin strings.
- It may be building JavaScript localization payloads that belong in
  `TypesenseConfig` or a dedicated localizer.

### Suggested refactor

Audit first. If the file is large because it contains a necessary locale map,
leave it alone. If it is doing multiple jobs (plugin i18n + JS payload
injection + locale detection), extract:

```text
Frontend/I18n.php              plugin string registration only
Frontend/WebAwesomeLocale.php  locale detection and mapping
```

Note: there is already a `source/js/webawesome-locale.ts`, so the JS side may
already be handled. Check whether the PHP file is duplicating that logic
server-side.

## Testing Strategy

Tests serve two purposes here: pinning current behavior before refactoring, and
giving ongoing confidence when new features are added. Both are worth investing
in, and the two goals reinforce each other — a test written to guard a refactor
stays useful forever.

### Current Setup

PR 0 has been completed. The plugin now has a fast PHPUnit unit-test setup that
does not bootstrap WordPress:

- `phpunit.xml.dist`
- `tests/bootstrap.php`
- `tests/TestCase.php`
- `tests/Unit/...`
- Composer scripts: `composer test`, `composer test:dox`, and
  `composer test:coverage`
- GitHub Actions workflow: `.github/workflows/test.yml`

The current stack is:

- **`phpunit/phpunit`** — test runner.
- **`Brain\Monkey`** — stubs and asserts WordPress functions such as
  `get_option`, `apply_filters`, and `sanitize_text_field`.
- **`Mockery`** — object mocks, especially for `$wpdb`.

The plugin now explicitly requires PHP 8.3:

- Composer: `"php": ">=8.3"`
- WordPress plugin header: `Requires PHP: 8.3`

The GitHub Actions workflow runs `composer test` on PHP 8.3 and PHP 8.4 for
pushes and pull requests targeting `dev` or `main`.

Current tests cover:

- Settings sanitizers.
- `SettingsRepository` defaults, coercion, clamping, and query-weight ordering.
- `DocumentBuilder` document shape and public filter hooks.
- `PinnedResults\Repository::delete()` current delete behavior.
- Database `maybeMigrate()` guards when the installed version is current.

There is one intentionally incomplete test for the desired future item 9
behavior: deleting a never-synced pinned-result rule should not mark all
remaining rules pending. Leave it incomplete until item 9 is implemented, then
remove the incomplete marker and make the behavior real.

### What To Test And When

#### Before PR 2 — Characterization tests for classes being split

Write these against the current code so they pin existing behavior. They will
break if the refactor accidentally changes something.

**`Admin/SettingsAjax.php`** — do not try to test every handler end to end.
Instead test the shared helper methods (nonce checks, permission checks,
JSON response shape) if they are extracted during the split.

**`CLI/IndexCommand.php`** — focus on argument validation and subcommand
registration, not the full indexing/rebuild flow. WP-CLI has a test mode via
`WP_CLI::set_logger()` that captures output without running a real site.

#### Before PR 3 — Characterization tests for Settings and App

Initial tests already cover `Settings` sanitizers and `SettingsRepository`
getters. Before splitting `Settings.php` or `App.php`, add focused tests for
any newly touched sanitizer, registration method, or bootstrap hook that is not
already covered.

#### Before or during PR 4 — Behavior tests for PinnedResults and sync state

**`PinnedResults\Repository::delete()` sync-state edge case** (see item 9):

```php
// Rule that was synced → delete should mark remaining rules pending
// Rule that was never synced → delete should not touch other rules
```

Prefer writing these as desired-behavior tests in the PR that fixes item 9. If
characterization tests are needed before a broader refactor, mark the current
all-rules-pending behavior as temporary and replace it with the desired tests
when applying the behavior fix. Do not permanently lock in the known bug.

**Database migration guards** — the current tests verify that `maybeMigrate()`
is a no-op when the stored version matches `DB_VERSION`. If future migrations
add versioned branches, add tests for the branch conditions before changing the
schema code.

#### For new features — Write tests first

When adding features such as search shortcuts, search notices, or query
suggestions, write the test before the implementation. Target:

- Repository methods: save, fetch, delete, sync-state transitions.
- REST controller: authorization, response shape, error cases.
- Any sanitizer or validator for the new settings.

This is the long-term payoff. A test per feature area means regressions surface
in CI, not in production.

### What Not To Test

- **Views and rendered HTML** — brittle and slow to write. Trust manual
  review for visual output.
- **Full AJAX handler integration** — the request/response cycle depends on
  WordPress internals that are hard to stub cleanly. Test the logic inside the
  handler, not the handler itself.
- **Full indexing/rebuild flow against a real Typesense server** — this
  belongs in an end-to-end suite, not unit tests. Out of scope for now.
- **WordPress core behavior** — trust `add_action`, `get_option`, etc. to work.
  Only test your own code.

### Running Tests

```bash
composer test
composer test:dox
composer test:coverage
./vendor/bin/phpunit --filter SettingsRepository
```

Use `composer test` for the normal pass/fail check. Use `composer test:dox` when
you want readable behavior names. Use `composer test:coverage` when Xdebug
coverage is available and you want to inspect which production files are
exercised.

## PR 0 — Test Infrastructure (Done)

Foundation work for the later refactor PRs.

Completed:

1. Added PHPUnit, Brain Monkey, and Mockery as dev dependencies.
2. Added `phpunit.xml.dist`.
3. Added `tests/bootstrap.php`, a base test case, and a minimal WordPress
   upgrade fixture.
4. Added Composer scripts for running the test suite, TestDox output, and
   coverage.
5. Added GitHub Actions for PHP 8.3 and PHP 8.4.
6. Added first characterization tests around low-dependency and refactor-prone
   behavior.

Verification:

```bash
composer test
./vendor/bin/phpunit
```

PR 0 intentionally did not split large production files. Keep adding tests
alongside later PRs when a refactor touches uncovered behavior.

## PR 1 — Pure Cleanup

Zero behavioral risk. Items: 1, 5, 6, 13.

1. Rename `typesense_search_uninstall_statistics_for_site()` to
   `typesense_search_uninstall_data_for_site()`.
2. Extract the advanced settings search-result behavior card into a partial.
3. Move the inline advanced settings JavaScript into `source/js/admin-settings.js`.
4. Remove the empty `source/php/AcfFields/` directory.
5. Update comments/docblocks that claim all option reads are centralized.

Suggested verification:

```bash
php -l uninstall.php
php -l source/php/Admin/Settings.php
npm run build
```

Then manually visit:

- Settings > Typesense Search > Advanced settings
- Settings > Pinned results
- Tools > Search log

## PR 2 — Large-File Splits

Moderate risk — these are large rewrites but behavior should be identical.
Items: 7, 16.

Depends on PR 0. Do not combine test infrastructure setup and large file splits
unless there is a strong reason.

1. Write characterization tests for any shared helpers in `SettingsAjax.php`
   (nonce/permission checks, JSON response helpers).
2. Split `SettingsAjax.php` into focused action classes under
   `source/php/Admin/Ajax/`.
3. Write characterization tests for CLI argument validation and subcommand
   registration in `IndexCommand.php`.
4. Split `CLI/IndexCommand.php` into action classes under
   `source/php/CLI/Actions/`.

Read both files fully before starting. Let the existing method groupings drive
the split boundaries.

Suggested verification:

```bash
composer test
php -l source/php/Admin/Ajax/*.php
php -l source/php/CLI/Actions/*.php
wp typesense --help
wp typesense index --dry-run
wp typesense rebuild --dry-run --skip-index
```

Manually trigger each admin-ajax action from the settings page UI.

## PR 3 — Bootstrap And Settings Restructure

Higher diff volume but low behavioral risk. Items: 2, 3.

1. Write characterization tests for `Settings` sanitizers and
   `SettingsRepository` getters before touching either class (see Testing
   Strategy).
2. Split `App.php` into feature bootstrap classes under `source/php/Bootstrap/`.
3. Split `Settings.php` by responsibility (registry, sanitizers, page, option
   keys).
4. Move `Settings.php` static helpers (`getIndexablePostTypes`,
   `isPdfToTextAvailable`, `isModularityAvailable`) to `SettingsRepository` or
   an `Environment` helper.

Keep `App::getRegistry()` working. Keep all option key constants accessible
from their existing fully-qualified names or add aliases.

Suggested verification:

```bash
php -l source/php/App.php
php -l source/php/Bootstrap/*.php
php -l source/php/Admin/Settings/*.php
npm run build
composer validate --no-check-publish
```

## PR 4 — Option Access And Typesense API

Touches runtime behavior. Items: 4, 8.

1. Write tests for `PinnedResults\Repository::delete()` sync-state behavior
   and database migration guards before changing either (see Testing Strategy).
2. Introduce `Typesense/AdminApi.php` and route raw HTTP calls through it.
3. Route plugin-owned `get_option()` calls in non-view runtime classes through
   `SettingsRepository`.

Do this after PR 2 so that the newly split Ajax action classes are the
injection targets, not the monolithic `SettingsAjax`.

Suggested verification:

```bash
composer test
php -l source/php/Typesense/AdminApi.php
```

Then test the full settings page AJAX flow end to end (connection test,
collection create, key generation, reindex).

## Notes For AI Agents

- Read the relevant files before editing. Do not refactor blindly based on this
  document.
- Avoid broad formatting churn.
- Keep option names, table names, hooks, REST routes, AJAX action names, and CLI
  commands stable unless the user explicitly asks for migrations.
- Run focused checks after each small refactor.
- If the branch has unpushed release commits, ask before changing release
  history.
- `CLI/IndexCommand.php` is the largest file in the plugin (~63 K). Read it
  fully before touching it. Do not assume subcommand boundaries from this
  document — derive them from the actual method groupings.
- `SettingsAjax.php` (~36 K) and `Settings.php` (~19 K) are the largest admin
  files. Both have handler logic and helper methods interleaved; read carefully
  before splitting.
- The PR ordering in this document matters. PR 2 (SettingsAjax split) should
  come before PR 4 (AdminApi) because smaller injection targets make the API
  wrapper cleaner.
