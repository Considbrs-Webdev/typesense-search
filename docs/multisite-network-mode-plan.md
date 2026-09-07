# Multisite network mode (GitHub issue #1)

Status: **implemented and locally verified** on 2026-09-07, based on
revision `10c5d6a`, for [issue #1](https://github.com/Considbrs-Webdev/typesense-search/issues/1).
The sections below retain the implementation contract. See
[multisite-verification.md](multisite-verification.md) for evidence and limitations.

## Implementation decisions

- Network settings use `Multisite/NetworkSettingsRepository`, a dedicated
  `Admin/NetworkSettingsPage` and native WordPress forms; no new JavaScript bundle.
- One site-local `typesense_network_state` option stores candidate, active and
  previous mappings. Activation atomically switches collection and key there.
  Legacy local collection/key options are preserved, not mirrored or migrated.
- Ownership is verified against a random token in Typesense collection metadata.
  An unrelated existing collection is never silently adopted or cleared.
- Preparation, candidate indexing and activation are explicit operations in the
  target site's own request. `wp typesense network` provides all three operations.
- Network activation initially falls back to ordinary WordPress search until
  each selected site is prepared, indexed, reviewed and activated. This is a
  controlled migration, not a promise of uninterrupted Typesense search.
- Context-sensitive consumers and caches use effective settings. Existing
  ConstantsLoader behavior remains intact for local activation; network mode
  explicitly rejects global collection/search-key constants.
- The shared-core `/wp` layout uses `Multisite/SiteUrls` for target-site admin
  actions, with a filter for custom routing. No installation rewrite is changed.

## Goal and scope

When network-activated, configure the Typesense connection and status in
Network Admin and select which sites use/index into Typesense. Each selected
site gets its own collection and search key. Content selection, search
appearance, synonyms, pinned results and statistics settings remain local.
Single-site and multisite with only local plugin activation retain their
existing behavior. The first version supports a small path-based network;
subdomains and mapped domains must still produce valid collection identities.

Keep the UI small, but do not substitute empty credentials for an activation
or authorization policy. The original code had direct option consumers and AJAX handlers accepting
explicit credentials. These paths now use effective settings and explicit policy guards.

## Settings and activation contract

Add a small NetworkSettingsRepository and a shared site-use policy. Detect
actual network activation using the plugin basename and the owning network's
`active_sitewide_plugins`, with an `is_multisite()` guard. Resolve the network
from the target site for operations accepting a site ID; reject IDs outside
the administrator's current network. Do not use `is_network_admin()` as the
runtime mode test.

| Mode/state | Effective behavior |
| --- | --- |
| Single-site or local activation only | Existing constant > local option behavior. |
| Network mode, site disabled or not ready | No Typesense search/indexing, even with old local values or constants. Preserve stored values. |
| Network mode, site enabled and ready | Shared connection constant > network option; no fallback to old local connection options. Collection/key come from the site's verified provisioned identity. |

Use network options for remote, admin key, frontend host and selected site IDs
(proposed keys: `typesense_network_remote`, `typesense_network_admin_key`,
`typesense_network_frontend_host`, `typesense_network_enabled_sites`). Empty
frontend host falls back to the effective remote. Keep existing local
`typesense_search_index_name` and `typesense_search_search_key` identifiers;
add minimal provisioned-state metadata for identity, target server and readiness.
Selection and readiness are distinct: a selected site with a provisioning error
must remain inactive until an explicit successful retry.

SettingsRepository exposes effective values and the shared policy. Empty
values may be a secondary safeguard, but callers must not infer permission
from the presence of credentials. Provisioning/network status needs privileged
access to shared configuration even when the target site is not ready; use an
explicit internal configuration path, not a public runtime-policy bypass.

### Constants and legacy reads

ConstantsLoader currently filters local reads and prevents writes for five
options. In network mode, global TYPESENSE_COLLECTION and TYPESENSE_SEARCH_KEY
must produce a clear conflict that blocks provisioning/use until removed or
replaced by an explicitly site-specific design. Do not silently save local
values that these constants will override. Connection constants remain
supported, but never bypass the disabled-site policy. Preserve all existing
constant behavior outside network mode.

Route ClientFactory::fromOptions(), isReady() and isReadyWithCollection()
through the same effective configuration/policy. Audit direct option reads,
including Frontend/Assets and views. Avoid global option filters that expose
a network admin key through old local forms. Prefer explicit resolution;
adapt ConstantsLoader so its existing filters do not defeat the contract.

## Administration and authorization

Add Network Admin tabs **Anslutning / Webbplatser / Status**, using
network_admin_menu and a dedicated network_admin_edit action. Saving requires
manage_network_options, nonce validation, sanitization and a network redirect.
Do not reuse the local options.php form for network options.

In network mode, local Connection/Status tabs show only a managed-state notice
and a suitable network-settings link for authorized users. Do not render the
shared admin key, even as a readonly password field. Stop registering network-
owned connection fields for local options.php writes in this mode.

Audit existing AJAX and REST actions on the server:

- Network connection changes, collection provisioning and search-key creation
  require manage_network_options in network mode, including old endpoints.
- Local content/indexing operations can retain manage_options, restricted to
  the current active site and its server-resolved collection.
- Validate nonce/authentication as appropriate, site ID and network membership
  before switching context. Never let a local administrator choose another
  collection or use the shared admin key by manipulating POST fields.
- Existing direct update_option writes, including SearchKeyActions, must honor
  ownership and constant locks. Hiding a form or withholding a nonce in the UI
  is not the authorization mechanism.
- Saved and constant credentials are used server-side for status/tests. Never
  send a masked password as an actual key, or return the shared key in JSON.

Show shared server status separately from each site's collection/key status.
A simple unpaginated list is acceptable for this deployment. Run remote checks
on explicit request, rather than probing every site on every page load. A
single-site retry/provision button is sufficient; no background queue is needed.

## Collection identity and environment changes

Proposed name: `{normalized-domain}[-{normalized-path}]__{environment}_b{blog_id}`.
For example `pitea-local-test2__local_b2`. Use the target site's canonical home
URL, never the incoming Host header. Use wp_get_environment_type(); document
that an unset environment defaults to production and configure the local test
environment explicitly.

Define deterministic ASCII normalization and a bounded readable prefix. Preserve
the environment and blog-ID suffix when truncating. The blog ID distinguishes
sites within one WordPress installation, including normalization collisions.
It does not guarantee uniqueness between independent installations sharing a
Typesense cluster: use a deployment namespace if that topology is needed.
Advanced IDN/alias handling is deferred; deterministic normalization is not.

Store the canonical URL, environment and Typesense target identity used during
provisioning. Before runtime use, compare them with current configuration.
On mismatch, block use of the old identity and show an explicit reprovisioning
requirement. Do not automatically rename/drop/rebuild on an ordinary request.
This must cover a production database copied into a correctly configured local
or staging environment. No resolver can distinguish a clone with identical URL,
environment and target configuration; document that deployment prerequisite.

## Provisioning, retries and transition

A small synchronous operation per site is sufficient. It must be idempotent:

1. Validate network authorization, target site, selected state, effective
   configuration and constant conflicts. Lock provision operations per site so
   concurrent requests cannot race to overwrite the active key/identity.
2. Establish target-site context and create fresh settings/client/capability
   instances. For context switches use try/finally with restore_current_blog().
3. Resolve the candidate identity. If a matching plugin-owned collection
   already exists, verify/reuse it; do not recreate or clear it. An unexpected
   existing collection requires explicit review rather than silent adoption.
4. Create a missing collection with the target site's schema and verify or
   generate a search key scoped to that exact collection. Persist enough state
   to retry after collection creation or a failed key operation. Do not rotate
   working keys on every settings save or re-enable.
5. Verify local persistence and key access before marking the site ready.
   On failure, report the failed stage and allow retry without deleting data.

switch_to_blog() changes database context, not loaded plugins, themes or their
registered schema filters. Run schema-sensitive provisioning and full indexing
in the target site's own authenticated request/CLI context when those hooks
matter. Do not claim that merely constructing SettingsRepository reloads them.
No network-wide indexing loop is required: existing CLI commands with --url
or target-site admin operations are sufficient.

For a fresh site, provision the empty collection/key and then index using the
existing actions. For an already configured site, show an explicit transition:
retain old local settings/index, select the shared connection and candidate
identity, provision and populate the replacement, sync synonyms/pinned results,
and verify before switching the active mapping. An advanced migration wizard
is deferred; this controlled sequence and preserving the old index are not.
A failed transition must not overwrite the old mapping with an incomplete pair.

Disabling stops runtime use, including when local credentials/constants exist.
It preserves remote data and keys; it does not revoke previously published keys.
Re-enabling reuses a valid identity/key, and reports stale or missing state for
explicit repair. Do not silently create duplicate resources.

## Runtime and lifecycle integration

Use the common policy at bootstrap where useful and at operations that can run
after a context switch. Audit save/delete indexing, DisabledContentPruner,
external strategy sync, CLI, AJAX and REST writes to Typesense. Existing guards
can be reused where proven sufficient; no file group is exempt in advance.
Do not rename public hooks, commands or routes as part of this work.

Disabled sites use ordinary WordPress search and do not load the Typesense quick
search behavior or publish its config. Review Templates, FrontendFeature,
Frontend/Assets and Frontend/TypesenseConfig together. Preserve local management
of stored configuration and retention of previously collected statistics;
turning off search must not disable necessary local cleanup.

ClientFactory::isReadyWithCollection() has a request-static cache;
TypesenseClientService and ServerCapabilities cache per instance. Key caches by
relevant site/configuration or rebuild/invalidate them across context changes.
Test A → B → A, including a disabled site, not only network provisioning.

New sites default to disabled. Exclude archived/spam/deleted sites from active
use. Audit activation/deactivation and statistics cron cleanup across affected
sites without expensive network-wide work on normal requests. Keep tables local.
Clean up newly introduced network options and provision metadata at uninstall;
repairing all historical uninstall omissions is a separate task. Uninstall must
not delete remote Typesense collections as an incidental side effect.

## Implementation map and sequence

1. Multisite/NetworkSettingsRepository, site-use policy, OptionKeys and plugin
   basename; wire through App and SettingsRepository without breaking existing
   no-argument repository construction sites.
2. ConstantsLoader, ClientFactory and cached services; align legacy consumers
   with the effective settings contract.
3. Multisite/CollectionNameResolver and SiteProvisioner with minimal persisted
   identity/readiness state, retries and transition handling.
4. NetworkAdminFeature plus dedicated network page/save controller and views;
   local SettingsRegistry/SettingsPage, AJAX guards/actions and admin-settings
   TypeScript as needed for server-side ownership and context-correct actions.
5. Runtime consumers under Bootstrap, Frontend, Templates, Indexing, CLI and
   REST; activation/retention/uninstall integration as required by the audit.
6. Tests and README: setup, constants, transition, environment changes,
   disabling/re-enabling, and target-site CLI examples.

## Deferred

- Network-wide bulk indexing, background queues and a multi-step recovery UI.
- Paginated network listing and large-network job orchestration.
- Cross-site search or shared collections/keys.
- Advanced IDN/alias naming controls and cross-installation cluster management.
- General cleanup of pre-existing uninstall omissions and unrelated refactors.

## Verification and completion criteria

- PHP syntax checks on changed PHP; existing composer test suite plus new
  behavior tests; npm run build after implementation. Do not hard-code a stale
  test count in the acceptance criteria.
- Unit tests: network vs local activation, constant precedence/conflicts,
  disabled sites with old values, naming/truncation and identity mismatches.
- Action tests: local admin cannot mutate network connection/key/collection,
  spoof a site ID, or bypass guards via old AJAX/options.php paths.
- Provisioning tests: existing collection, failure after collection creation,
  retry, persistence failure, concurrency guard and disable/re-enable.
- Integration: two real local sites, separate test Typesense resources, local
  and network admin users; correct search results and key A denied access to B.
- Verify save/delete, external sync and CLI --url affect only the active target;
  disabled sites use ordinary search even with prior credentials/quick search.
- Verify A → B → A cache/config isolation, site-specific schema hooks, local
  tables/retention, new-site defaults and network deactivation.
- Verify existing-site transition and staging clone mismatch; old index remains
  intact. Repeat key smoke checks with single-site/local plugin activation.

Prepare a second test site and confirm the test WordPress installation loads
this branch before implementation tests. Environment mutations and test runs
belong to the implementation task, not to this documentation review.
