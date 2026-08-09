# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.2] - 2026-08-09

### Fixed

- Fixed `wp typesense index --include-pdf` (and `--only-pdf`, `rebuild --include-pdf`) indexing PDF
  attachments even when PDF indexing was disabled in the content settings.
- Fixed a fatal error when saving or editing PDF attachments, caused by an incorrect internal
  settings check.

## [1.4.1] - 2026-07-01

### Fixed

- Single-segment statistics donut chart rendering.

### Changed

- Disabled content (unpublished/trashed/excluded posts) is now pruned from the Typesense index
  instead of being left behind.
- Small look-and-feel tweaks for pinned search results.

## [1.4.0] - 2026-06-29

### Changed

- Restructured frontend search imports.
- Modularized admin JS into shared utilities and per-page sub-modules.
- Converted `ServerCapabilities` to an instance class, injectable via `AdminApi`.
- Routed option reads through `SettingsRepository` and introduced `AdminApi` to centralise
  Typesense HTTP calls.

### Fixed

- Pinned-result delete sync-state: no longer marks all rules pending when deleting a rule that was
  never synced.

## [1.3.0] - 2026-06-26

### Added

- Pinned search results: pin specific documents to the top of search results for chosen queries.

## [1.2.0] - 2026-06-25

### Added

- Search statistics logging and reporting.
- Ability to index only PDF files or only external services via WP-CLI.

### Changed

- Restructured and reorganised the admin settings page for better flow.
- Refactored shared post index eligibility logic.

## [1.1.1] - 2026-06-23

### Fixed

- PDF attachments incorrectly resolved their section/parent during indexing.

## [1.1.0] - 2026-06-23

### Added

- Mobile quick search modal.

## [1.0.0] - 2026-06-19

### Added

- Initial release: Typesense-powered search for WordPress and Municipio.
- Indexing for posts and PDF attachments (via `pdftotext`), with support for external indexing
  strategies.
- WP-CLI commands for indexing, clearing, and rebuilding the Typesense collection.
- Admin settings page for connection, content, and appearance configuration.
- Frontend search UI with faceting and search statistics.
