# Typesense Search Refactor Roadmap

This document captures structural refactor ideas for a future branch. It is
intended for Michael, Codex, or another AI agent coming back to improve the
plugin later.

The main goal is maintainability. Do not treat this as a feature backlog and do
not change behavior unless a specific task asks for it. The plugin has grown a
lot through incremental feature work, and the code is still workable, but some
boundaries are now blurry.

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

1. Clean up obvious naming and view-level smells.
2. Split bootstrap into feature bootstraps.
3. Split settings/admin controllers.
4. Consolidate option access.
5. Consolidate Typesense HTTP/admin access.
6. Modularize admin JavaScript before adding new search-management features.

This order keeps early changes low-risk and prepares the codebase for future
features like search notices, shortcuts, and "Did you mean" suggestions.

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

`source/php/Admin/SettingsAjax.php` is large and handles many unrelated
admin-ajax actions:

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

### Suggested refactor

Create a tiny database module registry only if more tables are added:

```php
interface PluginTable
{
    public static function maybeMigrate(): void;
    public static function drop(): void;
}
```

Then a bootstrap can loop over feature tables. Do not add this abstraction just
for two tables unless you are already touching lifecycle code.

## 12. Capability Detection Is Static And Option-Based

### Current smell

`Typesense/ServerCapabilities.php` is static and reads options directly. It
caches the server version in a request-local static variable.

That is simple, but it makes dependency injection and tests harder. It also
means code that already has `SettingsRepository` still bypasses it.

### Suggested refactor

Convert it later to an instance service:

```php
new ServerCapabilities($settings, $typesenseAdminApi)
```

Keep a facade/static wrapper only if many callers need backward compatibility.

## 13. Source Tree Has An Empty Or Stray Directory

There is a `source/php/AcfFields` directory alongside `source/php/ACF`. It
appears empty from a shallow scan.

Suggested action:

- Confirm whether it is empty.
- Remove it if it is not needed.
- Standardize naming on either `ACF` or `AcfFields`, not both.

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

## Low-Risk First PR

A good first refactor branch could do only this:

1. Rename `typesense_search_uninstall_statistics_for_site()` to
   `typesense_search_uninstall_data_for_site()`.
2. Extract the advanced settings search-result behavior card into a partial.
3. Move the inline advanced settings JavaScript into `source/js/admin-settings.js`.
4. Update comments/docblocks that claim all option reads are centralized.

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

## Medium-Risk Follow-Up PR

1. Split `App.php` into feature bootstraps.
2. Split `SettingsAjax.php` by action group.
3. Route plugin option reads through `SettingsRepository` in non-view runtime
   classes.
4. Introduce a small Typesense admin API wrapper.

Suggested verification:

```bash
php -l source/php/App.php
npm run build
composer validate --no-check-publish
```

If PHPUnit coverage exists later, add tests around:

- settings sanitizers
- table lifecycle
- pinned result delete sync-state policy
- Typesense capability detection
- REST guardrails and no-cache headers

## Notes For AI Agents

- Read the relevant files before editing. Do not refactor blindly based on this
  document.
- Avoid broad formatting churn.
- Keep option names, table names, hooks, REST routes, AJAX action names, and CLI
  commands stable unless the user explicitly asks for migrations.
- Use `apply_patch` for manual edits.
- Run focused checks after each small refactor.
- If the branch has unpushed release commits, ask before changing release
  history.
