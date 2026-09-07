# Typesense Search Refactor Roadmap

Reviewed against local revision `10c5d6a` on 2026-09-07.
**Status: mostly implemented, not fully complete.** Keep this file while the
remaining item below is useful; it is not a prerequisite for new features.

## Remaining optional refactor

### Separate frontend config construction from script injection

`source/php/Frontend/TypesenseConfig.php` still handles locale mapping,
configuration construction, facets, filters, quick-search selectors and inline
script injection. A pure config builder plus a small localizer could improve
testability, but the current file is self-contained. Low urgency; split only
when a concrete change benefits from it. Preserve public filters and payloads.

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
