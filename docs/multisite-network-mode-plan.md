# Multisite network mode (GitHub issue #1)

Status: **plan, not yet implemented.** This document is the implementation
plan for `feature/multisite-network-mode`, written for review before code is
written.

## Context

The plugin is going onto a multisite install (path-based multisite — sites are
`example.com/sub-site/`, not `sub-site.example.com`) and the goal is to avoid
configuring the Typesense connection, collection, and search key separately on
every sub-site. [Issue #1](https://github.com/Considbrs-Webdev/typesense-search/issues/1)
asks for three things:

1. Move the connection and status tabs to a network-level configuration.
2. Use domain + environment as the collection name.
3. A network-level setting for which sites are enabled/indexed.

The issue also carries a very thorough analysis comment covering an idealized
full implementation (resumable provisioning with partial-failure recovery,
IDN-safe naming with collision detection, paginated site lists, exhaustive
uninstall across every subsystem, REST/CLI guards everywhere, etc). That
comment is a useful risk checklist, but building all of it is a multi-week
epic. This plan deliberately scopes down to a correct, secure v1 that satisfies
the 3 literal asks, and explicitly calls out what's deferred and why.

**Key simplification found during code exploration:** every consumer of
connection settings today (`ClientFactory::isReady()`, indexing strategies,
frontend search, quick search, the AJAX status checks) already treats an empty
remote/admin-key/collection as "not configured" and no-ops gracefully — this is
exactly how a fresh single-site install behaves before anyone fills in the
connection tab. So "this site is not enabled at the network level" doesn't need
new guards sprinkled through `IndexingHooks`, CLI, or AJAX — it's enough to make
the *settings resolution layer* return `''` for a disabled site and let the
existing graceful-no-op behavior take over. This avoids touching
`IndexingHooks.php`, `IndexingRegistry.php`, CLI actions, or REST at all.

## Scope for this branch

### In scope

- **Network detection.** Check actual network activation (network-activated
  plugin), not just `is_multisite()` — a network can have the plugin active
  only locally on one site, which must keep behaving exactly as single-site
  does today.
- **Settings resolution.** For the 3 *shared* connection fields (`remote`,
  `admin_key`, `frontend_host`): constant (existing `ConstantsLoader`) > network
  value (only if the current site is enabled) > local option (existing
  single-site behavior, unchanged when network mode doesn't apply).
- **Network admin page** (Anslutning / Webbplatser / Status) to configure the
  shared connection once and tick which sites are enabled.
- **Collection-name resolver**: `{normalized-domain}[-{normalized-path}]__{environment}_b{blog_id}`.
  The `_b{blog_id}` suffix guarantees uniqueness deterministically, without a
  collision-detection scheme (a deliberate simplification vs. the issue
  comment's IDN/collision-resolution proposal — revisit only if collisions
  actually become a problem).
- **Provisioning.** When a site is newly enabled: `switch_to_blog()` to it,
  resolve its collection name, create the collection with `Collection::create()`
  using *that site's own* `SettingsRepository` (so stemming/synonyms/pinned
  results are respected), generate a search key scoped to that collection with
  `ApiKey::generateSearchKey()`, and persist both as the site's normal local
  options (`typesense_search_index_name`, `typesense_search_search_key` — no
  new storage, no option renames, no schema migration).
- **Disabling** a site at the network level stops the network connection from
  applying to it (it falls back to "not configured" unless it has its own local
  override) but does **not** delete its remote collection or revoke its key —
  destructive cleanup stays a separate, explicit action.
- **Local per-site settings page.** When network mode is active for the current
  site, the Connection and Status tabs show a read-only "managed by the network
  admin" state instead of editable fields (same visual pattern already used for
  `ConstantsLoader`-defined fields). Content/Advanced/Quick search/Logging/
  Synonyms/Pinned-results tabs are untouched and stay fully local — this is
  intentional per the issue: "Innehållsval och sökutseende ligger kvar på
  respektive webbplats."
- **Tests** for network-mode detection, settings resolution, and the
  collection-name resolver, following the existing Brain Monkey conventions in
  `tests/TestCase.php` (which currently stubs no multisite functions at all —
  this is genuinely new stubbing, not an extension of something existing).
- **README** update documenting network mode.

### Explicitly deferred (do not build in this branch)

- Resumable/multi-step provisioning with a partial-failure recovery UI.
- IDN/domain-alias collision detection — sidestepped by the `_b{blog_id}`
  suffix.
- Paginated network site list / on-demand-only status checks — fine for a
  handful of municipal sub-sites; revisit if the network grows large.
- Extending `uninstall.php` for network options. Note: `uninstall.php` already
  doesn't clean up the connection options even in single-site mode today —
  that's a pre-existing gap, not something this issue needs to fix.
- Any change to CLI `--url` handling, REST routes, or `IndexingHooks` — not
  needed, per the simplification above.

## Current architecture (relevant parts, as of `dev`)

- `Services/SettingsRepository.php` — the runtime source of truth for options,
  currently all plain `get_option()` calls with no multisite awareness.
- `Admin/Settings/OptionKeys.php` — all WP option name constants live here.
- `ConstantsLoader.php` — registers `pre_option_{name}` / `pre_update_option_{name}`
  filters for 5 connection-related options when a matching PHP constant is
  defined, and exposes `ConstantsLoader::isDefinedAsConstant()` for views to
  render read-only fields. The network-value resolution should follow the same
  filter pattern, layered so the constant always wins.
- `Admin/Settings/SettingsPage.php` / `SettingsRegistry.php` — per-site admin
  page registered on `admin_menu`/`admin_init` (not network hooks). The
  connection tab form posts to `options.php` via `register_setting()` — this
  mechanism can't be reused as-is for a network page (`options.php` always
  targets the current site's options table), so the network page needs its own
  `network_admin_edit_{action}` handler with nonce + `manage_network_options`.
- `views/admin/settings-tabs/connection.php` — reads options directly and
  renders `readonly` + a notice when `ConstantsLoader::isDefinedAsConstant()`
  is true. The network-managed state should render the same way.
- `Admin/Ajax/*` (`ConnectionActions`, `CollectionActions`, `SearchKeyActions`,
  etc.) — all gated by `manage_options` via the `AjaxHelpers` trait, all
  site-scoped, no blog-id parameter anywhere. `SearchKeyActions::handleFixSearchKey()`
  writes directly via `update_option()`, bypassing `options.php`.
- `Typesense/ClientFactory.php` — `build()`/`fromOptions()`/`fromSettings()`/`isReady()`
  are stateless (safe to call repeatedly across `switch_to_blog()`).
  **`isReadyWithCollection()` uses a function-static cache that persists for
  the whole PHP process** — provisioning must not go through this method (or
  through `TypesenseClientService`/`ServerCapabilities`, which cache per
  instance) when looping over sites in one request. Provisioning should call
  `ClientFactory::build()` directly with explicit parameters instead.
- `Typesense/Collection.php` — `Collection::create($client, $name, $settings, $capabilities)`
  already accepts an injected `SettingsRepository`/`ServerCapabilities`, which
  is exactly what provisioning needs to build a site-correct schema without
  new schema logic.
- `Typesense/ApiKey.php` — `generateSearchKey($client, $collectionName)` scopes
  the key to exactly one collection name passed in by the caller — already
  multisite-safe, no changes needed.
- `uninstall.php` — already loops `get_sites()` + `switch_to_blog()`/`restore_current_blog()`
  for its existing (partial) per-site cleanup.
- `tests/TestCase.php` — Brain Monkey + Mockery, stubs `__`, `sanitize_key`,
  `sanitize_text_field`, `absint`, `wp_strip_all_tags`. No multisite functions
  stubbed anywhere in the test suite today.

## New/changed files

- `source/php/Multisite/NetworkSettingsRepository.php` (new) —
  - `isNetworkActivated(): bool` — checks `get_site_option('active_sitewide_plugins')`
    for our plugin basename directly, rather than calling
    `is_plugin_active_for_network()` (which requires `wp-admin/includes/plugin.php`,
    not loaded on the frontend/CLI). Needs a `TYPESENSESEARCH_BASENAME` constant
    defined in the main plugin file (`plugin_basename(__FILE__)`).
  - `getEnabledSiteIds(): int[]`, `isSiteEnabled(int $blogId): bool`.
  - `getNetworkRemote()/getNetworkAdminKey()/getNetworkFrontendHost()` reading
    new network options: `typesense_network_remote`, `typesense_network_admin_key`,
    `typesense_network_frontend_host`, `typesense_network_enabled_sites`.
  - Setters used only by the network admin save handler.
- `source/php/Multisite/CollectionNameResolver.php` (new) — `resolve(int $blogId): string`
  using `get_blog_details()`/`home_url()` for domain+path and
  `wp_get_environment_type()` for environment; ASCII-normalizes, truncates to a
  safe length, appends `_b{blogId}`.
- `source/php/Multisite/SiteProvisioner.php` (new) — `enable(int $blogId): void`
  (switch_to_blog → resolve name → `Collection::create()` → `ApiKey::generateSearchKey()`
  → persist as local options → `restore_current_blog()` in a `finally`) and
  `disable(int $blogId): void` (bookkeeping only — no remote deletion).
- `source/php/Services/SettingsRepository.php` — inject `NetworkSettingsRepository`;
  `getRemote()/getAdminKey()/getFrontendHost()` resolve constant > network (if
  enabled) > local option. `getCollectionName()`/`getSearchKey()` stay exactly
  as they are (provisioning already writes the right local values).
- `source/php/Admin/Settings/OptionKeys.php` — add the 4 new network option
  name constants.
- `source/php/Bootstrap/` — a `NetworkAdminFeature` (or extend `AdminFeature`,
  whichever fits better once the bootstrap classes are re-read at
  implementation time) registering `network_admin_menu` + a
  `network_admin_edit_{action}` handler (nonce + `manage_network_options`),
  mirroring the existing `SettingsPage`/`SettingsRegistry` split.
- `views/admin/network/*.php` (new) — network admin views for
  Anslutning/Webbplatser/Status, modeled on the existing settings-tab views.
- `views/admin/settings-tabs/connection.php` (and the status tab view) — when
  `NetworkSettingsRepository::isNetworkActivated()` is true for the current
  site, render the existing "locked" pattern instead of an editable form, with
  a link to the network settings page.
- `App.php` — construct `NetworkSettingsRepository` alongside the other shared
  services; pass it into `SettingsRepository` and the new admin feature.
- `tests/Unit/Multisite/*Test.php` (new).
- `README.md` — document network mode, the collection naming scheme, and that
  per-site indexing/search-appearance settings remain local.

## Verification plan

- `php -l` on every changed/new PHP file after each step.
- `composer test` — existing 74 tests must keep passing, plus the new
  multisite suite.
- `npm run build` — no JS changes expected, but check
  `source/js/admin-settings/` in case the connection/status tab UI needs a
  small update to reflect the read-only/network-managed state.
- Live check on a local multisite (`pitea.local`, path-based, 2 sites):
  network-activate the plugin, configure the shared connection once from
  Network Admin, enable both sites, confirm each gets its own collection name
  and that a search key from one site cannot query the other's collection.
  Confirm the local per-site settings page shows the read-only
  "managed by network" state for Connection/Status while Content/Advanced/etc.
  stay editable.

## Open questions for review

- Is the `_b{blog_id}` suffix on collection names acceptable, or is a
  human-readable-only name (with explicit collision handling) actually
  required for this deployment?
- Should the network "Webbplatser" list support deferred/paginated status
  checks now, or is a plain synchronous list fine given the expected site
  count for this network?
- Any objection to leaving `uninstall.php`'s existing gap (connection options
  never cleaned up) untouched rather than fixing it as a drive-by in this PR?
