# Multisite code review — 2026-09-07

Automated code review of `dev...feature/multisite-network-mode` (Claude Code,
`/code-review high`). Setup and operation are in
[README.md](../README.md#10-multisite-network-mode); the design contract is in
[multisite-network-mode-plan.md](multisite-network-mode-plan.md); test results
are in [multisite-verification.md](multisite-verification.md).

Findings are unverified beyond the reviewing agent's own reasoning — re-check
each before fixing.

## Should fix before relying on network policy

1. **REST endpoints bypass network policy**
   `source/php/PinnedResults/RestController.php:68` (check the synonyms REST
   controller too, same pattern likely applies) — `canManage()` only checks
   `current_user_can('manage_options')`, never `SettingsRepository::canUseTypesense()`
   / `NetworkSettingsRepository`. The AJAX handlers
   (`AjaxHelpers::requireNetworkPolicy`) explicitly block local admins on a
   disabled/unprepared network-mode site with a "managed by Network Admin"
   message; REST does not. A local site admin can create/delete pinned-result
   rules and synonyms via REST on a site the network admin has deselected —
   those rows accumulate locally and never sync until the site is enabled.

2. **`NetworkSettingsRepository::networkId()` can dereference `false`**
   `source/php/Multisite/NetworkSettingsRepository.php:20` — assumes
   `get_site(get_current_blog_id())` always returns an object, but `get_site()`
   can return `false`. The `??` still recovers a value, but the PHP warning
   ("Attempt to read property on bool") fires on every call to
   `isNetworkActivated()` / `selected()` / `connection()` / `identity()` — i.e.
   the entire network-mode gate. Fatal under an error handler that escalates
   warnings to exceptions.

## Worth fixing, less urgent

3. **Sites table in network admin recomputes identity itself**
   `views/admin/network/settings.php:47` instead of calling
   `NetworkSettingsRepository::identity()`. Risk that the Active/Ready status
   shown in the UI diverges from the real `canUse()` gate used everywhere else
   (AJAX, indexing, frontend) — e.g. if a per-site environment filter is in
   use, or `identity()`'s field set changes.

4. **`ProvisioningGateway::owns()` / `exists()` lack broad exception handling**
   `source/php/Multisite/ProvisioningGateway.php:22` — unlike the established
   `Collection::exists()` pattern (which also catches generic `\Exception`).
   `SiteProvisioner::prepare()` calls `exists()` then `owns()` on the same
   collection; a transient server error/timeout between the two calls
   surfaces as a generic "Typesense operation failed" instead of the intended
   recoverable retry flow.

5. **`AdminApi::request()` reimplements the policy check inline**
   `source/php/Typesense/AdminApi.php:78` — constructs its own
   `NetworkSettingsRepository` and re-derives the network-policy check instead
   of calling `$this->settings->canUseTypesense()`, even though `AdminApi`
   already receives `SettingsRepository` via constructor. A future change to
   `canUseTypesense()` (e.g. a maintenance-mode flag) would silently not apply
   to raw HTTP calls made through `AdminApi::request()`.

6. **`CollectionNameResolver::name()` truncation edge case**
   `source/php/Multisite/CollectionNameResolver.php:27` — if
   `wp_get_environment_type()` (via its filter, or a long
   `WP_ENVIRONMENT_TYPE`) returns a string long enough that the
   environment-type suffix alone exceeds 128 characters, `substr($prefix, 0,
   128 - strlen($suffix))` receives a negative length, and the final name can
   exceed Typesense's collection-name limit. Requires an unusually long
   env-type value to trigger — low practical likelihood, but worth a guard.

## Minor / cosmetic

7. `SiteProvisioner::prepare()` (`source/php/Multisite/SiteProvisioner.php:58`)
   calls `verify()` twice for the same candidate when a key already exists —
   once inside the "existing key" branch (line 48), then unconditionally
   again at line 58. Doubles Typesense requests on an already-provisioned,
   already-verified site.

8. `SiteProvisioner::save()` / `locked()`
   (`source/php/Multisite/SiteProvisioner.php:105`) write/read the network
   state and lock options directly via `update_option` / `get_option` /
   `add_option` / `get_blog_option` / `delete_blog_option` instead of through
   `NetworkSettingsRepository`, which CLAUDE.md designates as the runtime
   source of truth for plugin options. `NetworkSettingsRepository` currently
   only exposes read methods (`state()`, `mapping()`) — no write path.

9. The cache-invalidation fingerprint (`remote|adminKey|collectionName`) is
   reimplemented independently in three places —
   `TypesenseClientService::getClient()`, `ClientFactory::isReadyWithCollection()`,
   and `AdminApi::contextKey()` (which hashes only 2 fields) — instead of one
   shared method. A future field that should invalidate caches (e.g. a
   network/blog id, to catch a `switch_to_blog()` change) needs updating in
   three places by hand.

10. `IndexingRegistry::runExternalSync()` / `runAllExternalSyncs()`
    (`source/php/Indexing/IndexingRegistry.php:158`) return the same sentinel
    (`-1` / `[]`) both when network policy blocks the site and when the
    identifier is unknown / no strategies are registered. `App::getRegistry()`
    is a documented public accessor for CLI/external code, so callers can't
    distinguish "blocked by policy" from "nothing to do".

## Overall assessment

Core architecture (site state, collection naming, prepare/activate flow,
provisioning lock) looks sound and matches what's documented in the README
and the plan doc. Items 1 and 2 are real bugs worth fixing before trusting the
network policy fully — especially the REST gap, since it breaks the same
security boundary the AJAX layer deliberately enforces. The rest are
robustness and duplication concerns, not blockers.
