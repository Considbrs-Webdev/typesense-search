# Typesense Search Refactor Roadmap

Reviewed against local revision `10c5d6a` on 2026-09-07.
**Status: mostly implemented, not fully complete.** Keep this file while the
remaining items below are useful; it is not a prerequisite for new features.

## Remaining optional refactor

The agreed multisite UX changes and simplified setup flow are tracked separately
in [multisite-refactor.md](multisite-refactor.md). Read that checklist before
continuing multisite work; it records user decisions from 2026-09-07 and pending
bugs, and supersedes the earlier manual preparation workflow as the target design.

**Before merging this branch into `dev`:** remove all temporary documents created
during this work and every reference to them. This includes the multisite guide,
refactor checklist, verification report, network-mode plan and dated code review.
The full cleanup checklist is in `multisite-refactor.md`; remove this temporary
notice and the links above as part of that cleanup.

### Separate frontend config construction from script injection

`source/php/Frontend/TypesenseConfig.php` still handles locale mapping,
configuration construction, facets, filters, quick-search selectors and inline
script injection. A pure config builder plus a small localizer could improve
testability, but the current file is self-contained. Low urgency; split only
when a concrete change benefits from it. Preserve public filters and payloads.

## Optional multisite follow-ups

Retained after reassessing the
[multisite code review](multisite-code-review-2026-09-07.md) on 2026-09-07.
These are low-priority improvements, not established rollout blockers.

- [ ] **Reuse the shared policy in AdminApi.** In
  `source/php/Typesense/AdminApi.php`, use the injected settings repository's
  `canUseTypesense()` in `request()` instead of recreating the equivalent
  network-policy check. Preserve current behavior, including provisioning's
  internal candidate context.
- [ ] **Avoid verifying an existing valid key twice during prepare.** In
  `source/php/Multisite/SiteProvisioner.php`, a successful existing-key check
  is followed by another unconditional `verify()`. Remove the redundant
  search request while retaining verification of newly issued keys and repair
  of revoked keys.
- [ ] **Align network table readiness with runtime requirements.** In
  `views/admin/network/settings.php`, readiness does not require a nonempty
  collection, whereas `NetworkSettingsRepository::canUse()` does. Normal
  provisioning supplies it, but the UI should also handle incomplete state
  consistently. Preserve each row's target-site context; calling `identity()`
  directly in the loop would inspect the current site instead.
- [ ] **Make REST policy rejection explicit.** Consider checking
  `SettingsRepository::canUseTypesense()` in both pinned-results and synonyms
  REST permission checks, alongside `manage_options`. Existing feature guards
  already reject disabled/unprepared sites through effective connection and
  capability checks. This would make authorization easier to follow and allow
  a clearer managed-state error, rather than repair a demonstrated bypass.
- [ ] **Document blocked external-sync results.** Update the contract for
  `IndexingRegistry::runExternalSync()` / `runAllExternalSyncs()` to explain
  that policy rejection also returns `-1` / `[]`. Preserve public return types;
  richer diagnostics can wait until a caller needs them.

## Correctness work tracked elsewhere

Connection resolution, disabled-site behavior and context-sensitive client
caches are now handled by the multisite implementation. See
[multisite-network-mode-plan.md](multisite-network-mode-plan.md) and
[multisite-verification.md](multisite-verification.md). Local content preferences
and database lifecycle options remain site-owned; direct reads of those options
are not evidence of a network credential bypass.

## Completed structural work

The current source contains the planned feature bootstraps, split settings
classes, AJAX action classes, CLI action classes, AdminApi, injectable
ServerCapabilities and modular admin TypeScript. The pinned-result repository
also checks the deleted rule's sync state before marking other rules pending.
The uninstall helper rename and extracted advanced-settings partial are present.
Historical PR/test counts have been removed; Git history retains those details.

## Closed after audit

- **I18n.php size:** inspected. It groups translatable strings for frontend and
  admin bundles; it does not implement locale detection or script injection.
  No split justified solely by size. Locale mapping is in TypesenseConfig;
  browser locale loading is in source/js/typesense-search/webawesome-locale.ts.
- **Shared database abstraction:** still not justified merely by similar
  method names. There are now three local table owners (statistics, pinned
  results, synonyms), rather than the two described in the old roadmap.
- **Future feature layout:** guidance, not unfinished implementation. Use the
  existing Bootstrap and shared admin utility patterns documented in CLAUDE.md.
  Put list/rule management on dedicated admin pages, as described in
  [feature-roadmap.md](feature-roadmap.md).

## Working principles

Keep optional refactors behavior-preserving and reviewable. Preserve option
names, tables, public hooks, routes and commands unless a migration is part of
the requested change. Prefer existing shared utilities to new abstractions.
Run checks appropriate to implementation changes; this documentation audit
itself does not require application builds or database operations.
