# Multisite verification — 2026-09-07

Implementation is on `feature/multisite-network-mode`. Setup and operation are
in [README.md](../README.md#multisite-network-mode); the design contract is in
[multisite-network-mode-plan.md](multisite-network-mode-plan.md).

## Automated checks

- PHP 8.4: 119 PHPUnit tests, 217 assertions, all passing.
- PHP syntax checks for changed/new PHP files and `git diff --check` pass.
- Production frontend build passes. No frontend bundle change was needed.
- Swedish translation catalog compiles with `msgfmt --check`.

New tests cover disabled sites with legacy credentials, local activation,
constant conflicts, identity and environment changes, A → B → A resolution,
candidate context cleanup, naming/truncation, partial provisioning failure,
retries, collection ownership, revoked-key repair, restoring a prior identity,
persistence failure, lock exclusion, old AJAX denial, network capability
checks, forged target-site IDs and the shared-core admin URL layout.

## Local integration

WordPress multisite at `pitea.local`, main site 1 and Test 2 site 3; MySQL from
the installation's existing configuration. The test used a separate Typesense
30.2 container on port 18108 with temporary storage and a test-only admin key.
Existing Typesense collections on port 8108 were not changed.

- Prepared and activated separate collections with exact-collection search keys.
- Indexed main-site content: 1,314 indexed, 102 skipped, zero indexing failures.
  Test 2's sample page was indexed into its own collection.
- Confirmed Test 2's search key cannot search the main site's collection.
- Published a temporary page and verified its document in Test 2's collection;
  deleting the page removed its document.
- Disabled Test 2 and confirmed empty effective credentials, no indexing on
  save and no quick-search script, even with its local quick-search toggle on.
  Re-enabling preserved its key.
- Confirmed service/client isolation through site switches and that an
  environment mismatch blocks the old active mapping.
- Repeated preparation reused the working key; network-owned connection options
  were absent from the local settings registry.
- Browser-tested network site preparation and status checking through the
  target site's admin-post route, including success feedback after redirect.
  The subsite action URL is `/test2/wp-admin/`, while the main site uses
  `/wp/wp-admin/`. The plugin does not change WordPress rewrite rules.

The existing WP-CLI emitted PHP deprecation messages, and Modularity emitted
warnings for some main-site content during indexing. Neither caused an
indexing failure; those unrelated components were not modified.

## Cleanup and verification limits

Original network connection/selection, plugin activation and site options were
restored and checked after testing. The temporary Typesense container was
removed. Test 2 (site 3), its URL repair and unrelated cron entries were
preserved. Network mode is implemented but is **not left enabled** locally.
Legacy local connection/index/key values remain the fallback for local activation.

Authorization failure paths were tested with WordPress function doubles;
a separate real non-super-admin browser session was not used. Search-key and
search-result checks used the Typesense API; full interactive frontend search
was not separately exercised in every theme. No third-party external source,
PDF extraction, actual network uninstall, subdomain network or mapped-domain
installation was tested end to end. Existing tests/builds cover the unchanged
parts, but these are not claims of live coverage for every integration.

For rollout, configure the actual network connection, select sites, prepare,
index and review each candidate, then activate it. Keep the old indexes until
that review is complete. Network activation falls back to ordinary WordPress
search while a site is not ready. Large-network orchestration remains deferred.
