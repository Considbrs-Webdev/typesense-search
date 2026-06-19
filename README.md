# Typesense Search

A WordPress plugin that integrates [Typesense](https://typesense.org) as the search back-end for WordPress sites running the [Municipio](https://getmunicipio.com) theme. It keeps a Typesense collection in sync with your WordPress content in real-time and exposes a configurable front-end search UI.

- **Author:** Consid Borås AB
- **License:** MIT
- **Requires:** WordPress 5.6+, PHP 8.1+

---

## Table of contents

1. [What the plugin does](#1-what-the-plugin-does)
2. [Requirements](#2-requirements)
3. [Installation](#3-installation)
4. [Settings](#4-settings)
   - [4.1 PHP constants](#41-php-constants)
   - [4.2 Connection tab](#42-connection-tab)
   - [4.3 Settings tab (content)](#43-settings-tab-content)
   - [4.4 Facetting tab](#44-facetting-tab)
   - [4.5 Quick search tab](#45-quick-search-tab)
   - [4.6 Statistics tab](#46-statistics-tab)
   - [4.7 Status tab](#47-status-tab)
5. [Per-post controls](#5-per-post-controls)
6. [WP-CLI commands](#6-wp-cli-commands)
7. [How indexing works](#7-how-indexing-works)
   - [7.1 Architecture overview](#71-architecture-overview)
   - [7.2 Services layer](#72-services-layer)
   - [7.3 IndexingHooks](#73-indexinghooks)
   - [7.4 IndexingRegistry](#74-indexingregistry)
   - [7.5 IndexingStrategyInterface](#75-indexingstrategyinterface)
   - [7.6 IndexableDocument](#76-indexabledocument)
   - [7.7 Built-in strategies](#77-built-in-strategies)
   - [7.8 DocumentBuilder and the filter chain](#78-documentbuilder-and-the-filter-chain)
   - [7.9 Enrichers](#79-enrichers)
8. [Extensibility](#8-extensibility)
   - [8.1 Add or transform fields via DocumentBuilder filters](#81-add-or-transform-fields-via-documentbuilder-filters)
   - [8.2 Register a custom WordPress strategy](#82-register-a-custom-wordpress-strategy)
   - [8.3 Index external content](#83-index-external-content)
   - [8.4 Customise hit templates](#84-customise-hit-templates)
9. [WordPress hooks and filters reference](#9-wordpress-hooks-and-filters-reference)

---

## 1. What the plugin does

- Connects to a self-hosted or cloud Typesense instance.
- Automatically indexes WordPress posts (any post type), pages, and PDF files into a single Typesense collection whenever content is published, updated, unpublished, trashed, or deleted.
- Provides a configurable search page UI (Instantsearch-based) that supports faceting, pagination, hit highlighting, and result truncation.
- Provides a **quick-search** overlay that attaches to configurable CSS selectors on the front-end.
- Exposes WP-CLI commands for bulk indexing, dry-run previews, and index maintenance.
- Is designed to be extended: add fields to existing documents via WordPress filters, write custom indexing strategies for new content types, or index content from **external sources** (APIs, feeds, third-party systems) alongside WordPress content.

---

## 2. Requirements

| Requirement        | Notes                                           |
| ------------------ | ----------------------------------------------- |
| WordPress          | 5.6+ (`wp_after_insert_post` hook)              |
| PHP                | 8.1+ (union types, `readonly`, named arguments) |
| Typesense server   | Any self-hosted instance or Typesense Cloud     |
| `pdftotext` binary | Optional — required only for PDF indexing       |
| WP-CLI             | Optional — required only for CLI commands       |

---

## 3. Installation

1. Clone or copy the plugin into `wp-content/plugins/typesense-search/`.
2. Run `composer install` inside the plugin directory to install the PHP client.
3. Run `npm ci && npm run build` to compile front-end assets.
4. Activate the plugin from **Plugins** in the WordPress admin.
5. Navigate to **Settings → Typesense Search** and fill in the Connection tab (see §4).

---

## 4. Settings

The settings page is at **Settings → Typesense Search** and is split into six tabs.

---

### 4.1 PHP constants

All five connection settings can be overridden by defining PHP constants before WordPress loads the plugin. The recommended place is a dedicated config file included from `wp-config.php` (e.g. `wp-content/config/typesense.php`). This is the standard pattern in Municipio/Helsingborg-stad setups.

When a constant is defined:

- Its value is used **instead of** whatever is stored in the WordPress database.
- The corresponding field in **Settings → Connection** is rendered **read-only** so it cannot accidentally be overwritten from the UI.
- Any form submission that would change the value is silently a no-op.

#### Supported constants

| Constant                  | WordPress option                 | Description                                    |
| ------------------------- | -------------------------------- | ---------------------------------------------- |
| `TYPESENSE_HOST`          | `typesense_search_remote`        | Full URL to the Typesense server               |
| `TYPESENSE_FRONTEND_HOST` | `typesense_search_frontend_host` | Optional public host sent to the browser       |
| `TYPESENSE_COLLECTION`    | `typesense_search_index_name`    | Name of the Typesense collection               |
| `TYPESENSE_ADMIN_KEY`     | `typesense_search_admin_key`     | Full-access Admin API key (server-side only)   |
| `TYPESENSE_SEARCH_KEY`    | `typesense_search_search_key`    | Search-only key passed to front-end JavaScript |

#### Setup

Create a config file (e.g. `wp-content/config/typesense.php`) and include it from `wp-config.php`:

```php
<?php
// wp-content/config/typesense.php
define('TYPESENSE_HOST',       'https://search.example.com');
define('TYPESENSE_COLLECTION', 'my-wordpress-site');
define('TYPESENSE_ADMIN_KEY',  'your-admin-key');
define('TYPESENSE_SEARCH_KEY', 'your-search-only-key');
// define('TYPESENSE_FRONTEND_HOST', 'https://public.example.com'); // optional
```

> **Blank values** — a constant set to an empty string (`''`) is treated as "not set" and the database option is used instead.

---

### 4.2 Connection tab

These settings tell the plugin how to reach your Typesense instance.

| Setting                 | Option key                       | Description                                                                       |
| ----------------------- | -------------------------------- | --------------------------------------------------------------------------------- |
| Remote URL              | `typesense_search_remote`        | Base URL of your Typesense server, e.g. `https://search.example.com`              |
| Index (collection) name | `typesense_search_index_name`    | The Typesense collection to read from and write to                                |
| Admin API key           | `typesense_search_admin_key`     | Full-access key — used server-side for indexing and collection management         |
| Search API key          | `typesense_search_search_key`    | Read-only key — passed to the front-end JavaScript                                |
| Frontend host           | `typesense_search_frontend_host` | Optional override of the host sent to the browser (useful behind reverse proxies) |

The admin key is kept server-side. The search key is the only credential exposed to the browser.

### 4.3 Settings tab (content)

| Setting                  | Option key                                    | Description                                                                                             |
| ------------------------ | --------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| Post types               | `typesense_search_post_types`                 | Which public post types to index. Stored as an array of post-type slugs                                 |
| Index Modularity content | `typesense_index_modularity_content`          | Whether to also index content from [Modularity](https://github.com/helsingborg-stad/Modularity) modules |
| Index PDF files          | `typesense_search_index_pdf`                  | Enable PDF indexing via `pdftotext`. Requires the binary to be installed                                |
| Results per page         | `typesense_search_hits_per_page`              | Number of search hits shown per page on the full search results page (default: 10)                      |
| Debounce search          | `typesense_search_debounce`                   | Whether to debounce search-as-you-type queries                                                          |
| Debounce delay           | `typesense_search_debounce_delay`             | Milliseconds to wait after the last keystroke before firing a query (default: 300)                      |
| Highlight context tokens | `typesense_search_highlight_affix_num_tokens` | Number of words shown around a highlighted match in search snippets (default: 15)                       |
| Truncation string        | `typesense_search_truncator`                  | The string appended to truncated excerpts (default: `[...]`)                                            |
| Search field weights     | `typesense_search_query_by_weights`           | Relevance weight for each configured search field (1-5; defaults to 1 for every field)                  |

#### Search field weights

Use the **Search field weights** card to control how strongly each indexed field
contributes to result relevance. Each field has an independent scale from `1`
(lowest) to `5` (highest):

| Admin label        | Typesense field |
| ------------------ | --------------- |
| Title              | `title`         |
| Excerpt            | `excerpt`       |
| Content            | `content`       |
| Content type name  | `type_name`     |
| Extra search terms | `extra_terms`   |

The plugin passes the configured values to both full and quick searches as
Typesense's `query_by_weights` parameter. Typesense reads those values in the
same order as `query_by`: `title,excerpt,content,extra_terms,type_name`.

### 4.4 Facetting tab

Configure which fields can be used as facets in the search UI.

| Setting | Option key                | Description                                                                                                                                                |
| ------- | ------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Facets  | `typesense_search_facets` | Array of facet definitions. Each entry has: `field` (Typesense field name), `label` (UI label), `placeholder`, `display_as` (`dropdown` or `button_group`) |

### 4.5 Quick search tab

Quick search is a lightweight search overlay that attaches to any element on the page.

| Setting             | Option key                             | Description                                                                                                                                                 |
| ------------------- | -------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Enable quick search | `typesense_quick_search_enabled`       | Toggle the feature on or off                                                                                                                                |
| CSS selectors       | `typesense_quick_search_selectors`     | One or more CSS selectors the overlay binds to. Each entry has `selector` and `sibling` (bool — place the widget next to the element rather than inside it) |
| Results per page    | `typesense_quick_search_hits_per_page` | Number of results shown in the overlay (default: 5)                                                                                                         |

### 4.6 Statistics tab

Read-only overview of the Typesense collection: document count and index size. Uses the admin API key to query Typesense directly from the browser via an AJAX proxy.

### 4.7 Status tab

Checks whether the current configuration is valid and the collection exists. Can create the collection if it is missing.

---

## 5. Per-post controls

Every indexed post type records meta fields, managed via a meta box visible in the post editor.

| Meta key                         | Constant                              | Effect                                                                                                                                                           |
| -------------------------------- | ------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `_typesense_exclude`             | `MetaBox::META_EXCLUDE`               | Set to `'1'` to prevent this post from being indexed (or to remove it from the index if already present)                                                         |
| `_typesense_exclude_as_section`  | `MetaBox::META_EXCLUDE_AS_SECTION`    | Pages only. Set to `'1'` to prevent a top-level page from being used as the `top_most_parent` section for itself, descendant pages, and attached PDFs            |
| `_typesense_extra_terms`         | `MetaBox::META_EXTRA_TERMS`           | Free-text field included in the indexed document, allowing keywords that don't appear in the post body to influence search ranking                                |

The `_typesense_exclude` flag is honoured by all built-in strategies. Custom strategies should check it in their `shouldIndex()` implementation if the same per-post control is desired.

The `_typesense_exclude_as_section` flag keeps the page indexed, but clears its
`top_most_parent` value when the page is the top-level page in its tree. When
the setting is changed, the plugin re-indexes published descendant pages and
their attached PDFs so their section facets update immediately.

---

## 6. WP-CLI commands

The plugin registers a `typesense` command when WP-CLI is loaded. All subcommands run after WordPress is fully loaded (`--when after_wp_load`).

---

### `wp typesense index`

Bulk-indexes all published posts for the post types enabled in settings.

```bash
# Index everything enabled in settings
wp typesense index

# Preview without writing anything
wp typesense index --dry-run

# Index specific post types
wp typesense index --post-type=post,page

# Control memory usage on large sites
wp typesense index --batch-size=50 --yes

# Include PDF attachments from the media library
wp typesense index --include-pdf

# Also run all external strategies after indexing posts
wp typesense index --include-external --yes

# Slow down the progress bar for visual debugging
wp typesense index --dry-run --sleep=200
```

| Flag                  | Description                                                                      |
| --------------------- | -------------------------------------------------------------------------------- |
| `--post-type=<types>` | Comma-separated post-type slugs. Defaults to all types enabled in settings       |
| `--batch-size=<n>`    | Posts per database query. Defaults to all posts in one query                     |
| `--dry-run`           | Resolve strategies and check `shouldIndex()` but do not write to Typesense       |
| `--include-pdf`       | Also index PDF attachments via `pdftotext` (requires the binary to be installed) |
| `--include-external`  | After indexing posts (and PDFs), also run all registered external strategies     |
| `--yes`               | Skip the confirmation prompt                                                     |
| `--sleep=<ms>`        | Sleep after each post in milliseconds (useful for development)                   |

---

### `wp typesense rebuild`

Drops the Typesense collection, recreates it from the plugin schema, then optionally re-indexes all content in one operation. Use this whenever the Typesense collection schema needs to change (e.g. after modifying the `Municipio/TypesenseSearch/Collection/getSchema` filter).

```bash
# Full rebuild: drop schema, recreate, re-index everything
wp typesense rebuild

# Preview what would happen without making any changes
wp typesense rebuild --dry-run

# Reset schema only — re-index manually later with wp typesense index
wp typesense rebuild --skip-index --yes

# Rebuild and re-index only pages
wp typesense rebuild --post-type=page --yes

# Full rebuild including PDF attachments
wp typesense rebuild --include-pdf --yes

# Full rebuild including external strategies
wp typesense rebuild --include-external --yes
```

| Flag                  | Description                                                                            |
| --------------------- | -------------------------------------------------------------------------------------- |
| `--post-type=<types>` | Comma-separated post-type slugs to re-index. Defaults to all types enabled in settings |
| `--batch-size=<n>`    | Posts per database query during re-indexing. Defaults to all posts in one query        |
| `--skip-index`        | Drop and recreate the schema only; do not re-index any posts                           |
| `--dry-run`           | Report what would happen without writing anything to Typesense                         |
| `--include-pdf`       | Also index PDF attachments after the schema is recreated                               |
| `--include-external`  | Also run all registered external strategies after re-indexing posts                    |
| `--yes`               | Skip the confirmation prompt                                                           |
| `--sleep=<ms>`        | Sleep after each post in milliseconds during re-indexing                               |

---

### `wp typesense clear`

Removes indexed documents from the Typesense collection. Deletes are executed as a single bulk request per post type, so the operation is fast even for large collections.

```bash
# Clear all post types enabled in settings
wp typesense clear

# Preview without deleting anything
wp typesense clear --dry-run

# Remove only pages
wp typesense clear --post-type=page

# Remove every document from the collection regardless of settings
wp typesense clear --post-type=all --yes

# Clear posts and PDF documents (together)
wp typesense clear --include-pdf --yes

# Clear posts and external strategy documents (together)
wp typesense clear --include-external --yes

# Clear ONLY PDF attachments — no post types, no external strategies
wp typesense clear --only-pdf --yes

# Clear ONLY external strategy documents (all registered strategies)
wp typesense clear --only-external --yes

# Clear ONLY a single external strategy's documents
wp typesense clear --only-external=pitea-eservice --yes
```

| Flag                             | Description                                                                                                                                                                                                                                       |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `--post-type=<types>`            | Comma-separated post-type slugs to clear. Defaults to all types enabled in settings. Pass `all` to remove every document in the collection                                                                                                        |
| `--dry-run`                      | Count matching documents and print a summary without deleting anything                                                                                                                                                                            |
| `--include-pdf`                  | Also clear PDF attachment documents (`type=attachment`) alongside post types                                                                                                                                                                      |
| `--include-external`             | Also clear all documents belonging to registered external strategies alongside post types (ignored when `--post-type=all`)                                                                                                                        |
| `--only-pdf`                     | Clear **only** PDF attachment documents; skip the post-type loop entirely. Cannot be combined with `--post-type` or `--only-external`                                                                                                             |
| `--only-external[=<identifier>]` | Clear **only** external strategy documents. Without a value, all strategies are targeted. With a value (e.g. `--only-external=pitea-eservice`), only that strategy's documents are removed. Cannot be combined with `--post-type` or `--only-pdf` |
| `--yes`                          | Skip the confirmation prompt                                                                                                                                                                                                                      |
| `--sleep=<ms>`                   | Sleep between post-type operations in milliseconds                                                                                                                                                                                                |

---

### `wp typesense list-external`

Lists all external indexing strategies registered by third-party plugins via the `Municipio/TypesenseSearch/RegisterStrategies` action. Use the printed identifiers with `sync-external` or `clear --only-external`.

```bash
# List all registered external strategies
wp typesense list-external
```

> This command takes no flags.

---

### `wp typesense sync-external`

Fetches and upserts documents from all registered external indexing strategies (or a single named one). External strategies are registered by third-party plugins via the `Municipio/TypesenseSearch/RegisterStrategies` action and have no WordPress lifecycle hooks — syncing must be triggered explicitly here or via WP-Cron.

```bash
# Sync all registered external strategies
wp typesense sync-external

# Sync only one strategy by its identifier
wp typesense sync-external pitea-eservice

# Preview registered strategies without fetching or writing anything
wp typesense sync-external --dry-run
```

| Argument / Flag  | Description                                                                                           |
| ---------------- | ----------------------------------------------------------------------------------------------------- |
| `[<identifier>]` | Optional strategy identifier (e.g. `pitea-eservice`). Omit to sync all registered external strategies |
| `--dry-run`      | List registered strategies without fetching or upserting anything                                     |
| `--yes`          | Skip the confirmation prompt                                                                          |

---

## 7. How indexing works

This section describes the entire indexing pipeline from a post save through to a Typesense document upsert. Understanding it is essential before writing custom strategies or enrichers.

### 7.1 Architecture overview

```
WordPress lifecycle event
        │
        ▼
  IndexingHooks          ← listens to wp_after_insert_post, trashed_post,
        │                   before_delete_post
        ▼
  IndexingRegistry       ← holds all registered strategies; routes each post
        │                   to the correct one via supports()
        ▼
  IndexingStrategyInterface
        ├─ shouldIndex()  ← eligibility check (post status, settings, meta flags)
        ├─ buildDocument()← assembles IndexableDocument from the post
        └─ index() / deindex() ← upsert or delete in Typesense
                │
        ┌───────┴────────┐
        ▼                ▼
TypesenseClientService   SettingsRepository
(cached client)          (typed option reads)
```

### 7.2 Services layer

Three shared services are built once by `App` and injected into every component that needs them.

| Class                    | Namespace                  | Responsibility                                                                                                                                                                                                                           |
| ------------------------ | -------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `SettingsRepository`     | `TypesenseSearch\Services` | Typed, default-aware getters for every WordPress option used by the plugin. Replaces scattered `get_option()` calls throughout the codebase.                                                                                             |
| `TypesenseClientService` | `TypesenseSearch\Services` | Lazily builds and caches the `\Typesense\Client` for the lifetime of the request. Consumers call `getClient()` — credentials are only read once even if dozens of strategies or hooks call it.                                           |
| `ErrorLogLogger`         | `TypesenseSearch\Logger`   | Default implementation of `LoggerInterface` that writes to PHP's `error_log()`. Debug messages are suppressed unless `WP_DEBUG` is enabled. Swap it for any other implementation by passing a different `LoggerInterface` to strategies. |

**Replacing the logger** — if you want to route plugin log messages to a custom destination (e.g. Sentry, a file, or a test spy), implement `LoggerInterface` and pass your implementation when registering strategies:

```php
add_action(
    'Municipio/TypesenseSearch/RegisterStrategies',
    function (
        \TypesenseSearch\Indexing\IndexingRegistry $registry,
        \TypesenseSearch\Services\TypesenseClientService $clientService,
        \TypesenseSearch\Services\SettingsRepository $settings,
        \TypesenseSearch\Logger\LoggerInterface $logger
    ): void {
        $registry->register(new MyCustomStrategy($clientService, $settings, new MySentryLogger()));
    },
    10, 4
);
```

### 7.3 IndexingHooks

`IndexingHooks` wires WordPress actions to the registry during bootstrap and also exposes its own actions for external code.

#### Hooks the plugin listens to

| WordPress hook                       | When it fires                                     | What the plugin does                                                                                                                                       |
| ------------------------------------ | ------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `wp_after_insert_post` (priority 20) | After a post **and all its meta** are fully saved | If `post_status === 'publish'`: call `shouldIndex()` → `index()` (or `deindex()` if excluded). If transitioning **away** from `publish`: call `deindex()`. |
| `trashed_post`                       | Post moved to the Trash                           | `deindex()`                                                                                                                                                |
| `before_delete_post`                 | Post permanently deleted                          | `deindex()`                                                                                                                                                |

Priority 20 on `wp_after_insert_post` is intentional — it ensures all meta boxes have written their values before `shouldIndex()` reads them.

#### Hooks the plugin exposes for external use

External plugins and themes can trigger indexing operations without depending on any internal class:

| Action hook                     | Parameter      | What it does                                                                                                                                                                        |
| ------------------------------- | -------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `typesense_search/index_post`   | `int $post_id` | Resolves the strategy for the given post and runs the same `shouldIndex()` → `index()` / `deindex()` logic as `wp_after_insert_post`. The post must be published — no-op otherwise. |
| `typesense_search/deindex_post` | `int $post_id` | Removes the document for the given post ID from the index. Safe to call even if the document does not exist.                                                                        |

```php
// Re-index a post after your plugin changes data that affects the index
do_action('typesense_search/index_post', $post_id);

// Explicitly remove a post from the index
do_action('typesense_search/deindex_post', $post_id);
```

PDF attachments have their own lifecycle hooks (`add_attachment`, `edit_attachment`, `delete_attachment`) registered by `PdfIndexingStrategy::registerHooks()`.

### 7.4 IndexingRegistry

The registry is the central routing table. It holds two separate sets of strategies:

- **WordPress strategies** (`IndexingStrategyInterface`) — event-driven; one handles each post saved by WordPress.
- **External strategies** (`ExternalIndexingStrategyInterface`) — pull-driven; triggered explicitly by cron, CLI, or any other mechanism. See §7.6 and §8.3.

Built-in registration order (order matters — first match wins for WordPress strategies):

1. `PdfIndexingStrategy` — matches PDF attachments
2. `PostIndexingStrategy` — matches everything else that is not an attachment

### 7.5 IndexingStrategyInterface

Every WordPress indexing strategy implements this contract:

| Method                                                    | Responsibility                                                                         |
| --------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| `getIdentifier(): string`                                 | Unique slug (e.g. `'post'`, `'pdf'`). Used for registry lookups and log messages       |
| `supports(\WP_Post $post): bool`                          | Is this strategy the right _type_ for this post? (e.g. "is it a PDF?")                 |
| `shouldIndex(\WP_Post $post): bool`                       | Does this specific post qualify for indexing right now? (status, settings, meta flags) |
| `buildDocument(\WP_Post $post): IndexableDocument\|false` | Build the document to upsert. Return `false` to abort                                  |
| `index(\WP_Post $post): bool`                             | Upsert the document into Typesense                                                     |
| `deindex(int $postId): bool`                              | Delete the document from Typesense                                                     |
| `registerHooks(): void`                                   | Wire up any additional WordPress hooks this strategy needs                             |

`AbstractIndexingStrategy` provides default `index()` and `deindex()` implementations (upsert and delete via the Typesense PHP client) so concrete strategies only need to implement `supports()`, `shouldIndex()`, `buildDocument()`, and optionally `registerHooks()`.

### 7.6 IndexableDocument

`IndexableDocument` is an immutable value object returned by `buildDocument()`. It guarantees that every document sent to Typesense has at minimum a non-empty `id` and `title` field (both required by Typesense).

```php
$doc = new IndexableDocument([
    'id'    => (string) $post->ID,
    'title' => $post->post_title,
    'url'   => get_permalink($post),
    // ...
]);

// Non-destructive update (returns a new instance)
$doc = $doc->with('author', get_the_author());

// Pass to Typesense
$doc->toArray();
```

### 7.7 Built-in strategies

#### PostIndexingStrategy (`'post'`)

Handles all non-attachment post types. A post is indexed when:

1. Its post type is enabled in **Settings → Typesense Search → Settings**.
2. `post_status === 'publish'`.
3. `_typesense_exclude` is not set to `'1'`.

The result at step 3 is filterable via `PostIndexingStrategy::FILTER_SHOULD_INDEX` (`Municipio/TypesenseSearch/Indexer/shouldIndex`).

Document fields built by `DocumentBuilder::build()` (see §7.7):

| Field                 | Source                                            |
| --------------------- | ------------------------------------------------- |
| `id`                  | `$post->ID` (string)                              |
| `title`               | `post_title`                                      |
| `content`             | `the_content` filter output, HTML-stripped        |
| `excerpt`             | `get_the_excerpt()`, processed by `ExcerptHelper` |
| `url`                 | `get_permalink()`                                 |
| `type`                | `post_type`                                       |
| `type_name`           | Post-type label                                   |
| `date`                | `post_date_gmt` as Unix timestamp                 |
| `post_date_formatted` | Formatted using the site's date format            |
| `thumbnail`           | Medium-size featured image URL                    |
| `extra_terms`         | `_typesense_extra_terms` meta value               |

#### PdfIndexingStrategy (`'pdf'`)

Handles `attachment` posts with `post_mime_type === 'application/pdf'`. A PDF is indexed when:

1. **Settings → Index PDF files** is enabled.
2. The `pdftotext` binary is available on the server.
3. `_typesense_exclude` is not set to `'1'`.

Text is extracted via `pdftotext` and capped at `DEFAULT_MAX_CONTENT_LENGTH` (50 000 characters), overridable via `PdfIndexingStrategy::FILTER_MAX_CONTENT_LENGTH`.

Additional PDF document field:

| Field             | Source                                                             |
| ----------------- | ------------------------------------------------------------------ |
| `top_most_parent` | Title of the top-level ancestor of the page the PDF is attached to |

### 7.8 DocumentBuilder and the filter chain

`DocumentBuilder::build()` assembles the document array for a WordPress post and passes it through two WordPress filter layers before wrapping it in `IndexableDocument`.

| Filter hook                                                   | Receives                           | Fires                           |
| ------------------------------------------------------------- | ---------------------------------- | ------------------------------- |
| `Municipio/TypesenseSearch/DocumentBuilder/build`             | `(array $document, WP_Post $post)` | Every post, regardless of type  |
| `Municipio/TypesenseSearch/DocumentBuilder/{post_type}/build` | `(array $document, WP_Post $post)` | Only posts of the matching type |

Both filters receive and must return a plain `array`. `IndexableDocument` is created after all filters have run.

### 7.9 Enrichers

Enrichers are classes that hook into the `DocumentBuilder` filter chain at bootstrap to add fields to specific post types. The plugin ships three:

| Enricher             | Post type        | Fields added                                                               |
| -------------------- | ---------------- | -------------------------------------------------------------------------- |
| `PageEnricher`       | `page`           | `top_most_parent` (top-level ancestor title), `path` (breadcrumb string)   |
| `JobPostingEnricher` | job posting type | Structured fields for job listings                                         |
| `ModularityEnricher` | all types        | Appends Modularity module content to `content` when the setting is enabled |

---

## 8. Extensibility

There are three extension levels, from lightest to most powerful.

### 8.1 Add or transform fields via DocumentBuilder filters

Use this when you want to add extra fields to existing documents without touching any plugin code. No new class is needed.

```php
// Add a field to every indexed post
add_filter(
    'Municipio/TypesenseSearch/DocumentBuilder/build',
    function (array $document, \WP_Post $post): array {
        $document['author'] = get_the_author_meta('display_name', $post->post_author);
        return $document;
    },
    10,
    2
);

// Add a field only to documents of post_type "event"
add_filter(
    'Municipio/TypesenseSearch/DocumentBuilder/event/build',
    function (array $document, \WP_Post $post): array {
        $document['event_date'] = get_post_meta($post->ID, '_event_date', true);
        return $document;
    },
    10,
    2
);
```

### 8.2 Register a custom WordPress strategy

Use this when a post type requires completely custom eligibility logic or a custom document shape that cannot be achieved with DocumentBuilder filters alone.

#### Step 1 — Write the strategy

```php
namespace MyPlugin\Search;

use TypesenseSearch\Indexing\IndexableDocument;
use TypesenseSearch\Indexing\Strategies\AbstractIndexingStrategy;

class ProductIndexingStrategy extends AbstractIndexingStrategy
{
    public function getIdentifier(): string
    {
        return 'product';
    }

    public function supports(\WP_Post $post): bool
    {
        return $post->post_type === 'product';
    }

    public function shouldIndex(\WP_Post $post): bool
    {
        // Only index products that are in stock
        return $post->post_status === 'publish'
            && get_post_meta($post->ID, '_stock_status', true) === 'instock';
    }

    public function buildDocument(\WP_Post $post): IndexableDocument|false
    {
        $price = get_post_meta($post->ID, '_price', true);
        if ($price === '') {
            return false; // skip products without a price
        }

        return new IndexableDocument([
            'id'       => (string) $post->ID,
            'title'    => $post->post_title,
            'content'  => wp_strip_all_tags($post->post_content),
            'excerpt'  => get_the_excerpt($post),
            'url'      => get_permalink($post),
            'type'     => 'product',
            'type_name'=> __('Product', 'my-plugin'),
            'price'    => (float) $price,
        ]);
    }
}
```

`AbstractIndexingStrategy` provides working `index()` and `deindex()` implementations, so you don't need to write those.

Inside a strategy, the injected logger is accessible as `$this->logger` and the settings repository as `$this->getSettings()`.

#### Step 2 — Register via the action hook

```php
add_action(
    'Municipio/TypesenseSearch/RegisterStrategies',
    function (
        \TypesenseSearch\Indexing\IndexingRegistry $registry,
        \TypesenseSearch\Services\TypesenseClientService $clientService,
        \TypesenseSearch\Services\SettingsRepository $settings,
        \TypesenseSearch\Logger\LoggerInterface $logger
    ): void {
        // Register before PostIndexingStrategy if your type could otherwise
        // be caught by the generic post handler first.
        $registry->register(new \MyPlugin\Search\ProductIndexingStrategy($clientService, $settings, $logger));
    },
    10, 4
);
```

The action fires after the built-in strategies (`pdf`, `post`) are already in the registry. If your strategy's `supports()` could overlap with `PostIndexingStrategy`, pass a priority lower than the default (i.e. `add_action(..., ..., 5)`) to ensure it is registered — and therefore evaluated — first.

#### Step 3 — Control `shouldIndex` from outside

The default filter on `shouldIndex` can be used from any theme or plugin:

```php
// Prevent a specific post from being indexed without touching the meta box
add_filter(
    \TypesenseSearch\Indexing\Strategies\PostIndexingStrategy::FILTER_SHOULD_INDEX,
    function (bool $shouldIndex, \WP_Post $post): bool {
        if ($post->post_type === 'post' && has_tag('no-index', $post)) {
            return false;
        }
        return $shouldIndex;
    },
    10,
    2
);
```

### 8.3 Index external content

Use this when the content you want to index does not come from WordPress at all — for example, an e-services portal, an open-data API, a legacy CMS, or any third-party system.

External strategies implement `ExternalIndexingStrategyInterface` and are **pull-driven**: they fetch data on demand rather than reacting to WordPress lifecycle events. They are registered separately on the registry via `registerExternal()` and triggered by WP-Cron, WP-CLI, or any other explicit call.

Because external documents share the same Typesense collection as WordPress posts, **document IDs must be namespaced** (e.g. `'eservice-42'`) to avoid collisions with WordPress post IDs (which are plain integers).

#### Step 1 — Write the strategy

Extend `AbstractExternalIndexingStrategy`. You only need to implement three methods:

```php
namespace MyPlugin\Search;

use TypesenseSearch\Indexing\IndexableDocument;
use TypesenseSearch\Indexing\Strategies\AbstractExternalIndexingStrategy;

class EServiceIndexingStrategy extends AbstractExternalIndexingStrategy
{
    public const CRON_HOOK = 'myplugin_sync_eservices';

    // ── Identity ────────────────────────────────────────────────────────────

    public function getIdentifier(): string
    {
        return 'eservice';
    }

    // ── Hook registration ───────────────────────────────────────────────────

    /**
     * Schedule a daily WP-Cron sync and wire it to syncAll().
     * Called automatically by IndexingRegistry::registerAllHooks().
     */
    public function registerHooks(): void
    {
        add_action('init', function (): void {
            if (!wp_next_scheduled(self::CRON_HOOK)) {
                wp_schedule_event(time(), 'daily', self::CRON_HOOK);
            }
        });

        add_action(self::CRON_HOOK, [$this, 'syncAll']);
    }

    // ── Data fetching ───────────────────────────────────────────────────────

    /**
     * Fetch all items from the external source.
     * May return any iterable — array, Generator, or Traversable.
     */
    protected function fetchItems(): iterable
    {
        $response = wp_remote_get('https://api.example.com/eservices', ['timeout' => 15]);

        if (is_wp_error($response)) {
            $this->logger->error('[EService] API error: ' . $response->get_error_message());
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        return $data['items'] ?? [];
    }

    // ── Document building ───────────────────────────────────────────────────

    /**
     * Convert one raw item into an IndexableDocument.
     * Return false to skip the item.
     */
    protected function buildDocument(mixed $item): IndexableDocument|false
    {
        if (empty($item['id']) || empty($item['title'])) {
            return false;
        }

        return new IndexableDocument([
            'id'        => $this->getExternalId($item),   // MUST be namespaced
            'title'     => (string) $item['title'],
            'content'   => (string) ($item['description'] ?? ''),
            'excerpt'   => (string) ($item['short_description'] ?? ''),
            'url'       => (string) ($item['url'] ?? ''),
            'type'      => 'eservice',
            'type_name' => __('E-service', 'my-plugin'),
            'date'      => isset($item['updated_at'])
                               ? (int) strtotime($item['updated_at'])
                               : 0,
        ]);
    }

    /**
     * Return the namespaced Typesense document ID for a raw item.
     * Must match the 'id' value set in buildDocument().
     */
    protected function getExternalId(mixed $item): string
    {
        return 'eservice-' . $item['id'];
    }
}
```

`AbstractExternalIndexingStrategy` provides working `syncAll()` and `deindex()` implementations. `syncAll()` iterates `fetchItems()`, calls `buildDocument()` on each item, and upserts the result. Individual failures are logged and skipped so the rest of the batch completes.

Notice that `$this->logger` is already available in the strategy for logging — no `error_log()` calls needed.

#### Step 2 — Register via the action hook

```php
add_action(
    'Municipio/TypesenseSearch/RegisterStrategies',
    function (
        \TypesenseSearch\Indexing\IndexingRegistry $registry,
        \TypesenseSearch\Services\TypesenseClientService $clientService,
        \TypesenseSearch\Services\SettingsRepository $settings,
        \TypesenseSearch\Logger\LoggerInterface $logger
    ): void {
        $registry->registerExternal(new \MyPlugin\Search\EServiceIndexingStrategy($clientService, $settings, $logger));
    },
    10, 4
);
```

After registration, `registerAllHooks()` will call `registerHooks()` automatically, scheduling the cron event.

#### Step 3 — Trigger syncs

**Automatic** — the cron event set up in `registerHooks()` fires daily.

**Manual via PHP:**

```php
$registry = \TypesenseSearch\App::getRegistry();

// Sync one strategy
$count = $registry->runExternalSync('eservice');  // returns items indexed

// Sync all external strategies
$results = $registry->runAllExternalSyncs();  // ['eservice' => 42, ...]
```

**Remove a single external document:**

```php
$registry->getExternal('eservice')->deindex('eservice-42');
```

#### The full contract (`ExternalIndexingStrategyInterface`)

| Method                              | Responsibility                                                              |
| ----------------------------------- | --------------------------------------------------------------------------- |
| `getIdentifier(): string`           | Unique slug (e.g. `'eservice'`)                                             |
| `syncAll(): int`                    | Fetch all items, build and upsert documents. Returns count of items indexed |
| `deindex(string $externalId): bool` | Delete one document by its namespaced ID                                    |
| `registerHooks(): void`             | Wire cron events, admin actions, or any other WordPress triggers            |

### 8.4 Customise hit templates

The search results page renders each hit using a small HTML snippet called a **hit template**. Templates are compiled from Blade view files and injected into the page as `<template>` elements. The front-end JavaScript selects the right template for each hit based on the document's `post_type` and replaces placeholder tokens with live values.

#### Built-in templates

| Template key | Blade view file                           | Best used for                                        |
| ------------ | ----------------------------------------- | ---------------------------------------------------- |
| `default`    | `templates/hits/hit-default.blade.php`    | Any post without a featured image                    |
| `noimage`    | `templates/hits/hit-noimage.blade.php`    | Explicitly image-free cards (identical to `default`) |
| `image`      | `templates/hits/hit-image.blade.php`      | Posts with a featured image (`thumbnail` field)      |
| `jobposting` | `templates/hits/hit-jobposting.blade.php` | Structured job-listing cards with a validity date    |

#### Placeholder tokens

Tokens are `{UPPER_SNAKE_CASE}` strings embedded in the template HTML. The JavaScript search layer replaces each token with the corresponding value from the Typesense hit document before inserting the card into the DOM.

##### Core tokens (always available)

| Token                        | Source field in document | Description                                       |
| ---------------------------- | ------------------------ | ------------------------------------------------- |
| `{SEARCH_HIT_LINK}`          | `url`                    | Full permalink to the post                        |
| `{SEARCH_HIT_ARIA_LABEL}`    | `title`                  | Accessible label on the card anchor               |
| `{SEARCH_HIT_HEADING}`       | `title` (highlighted)    | Post title, with Typesense highlights applied     |
| `{SEARCH_HIT_SUBHEADING}`    | `type_name`              | Human-readable post-type label                    |
| `{SEARCH_HIT_EXCERPT}`       | `excerpt` (highlighted)  | Snippet with highlights, truncated                |
| `{SEARCH_HIT_DATE}`          | `post_date_formatted`    | Formatted publication date                        |
| `{SEARCH_HIT_PATH}`          | `path`                   | Breadcrumb string (pages only, otherwise empty)   |
| `{SEARCH_HIT_IMAGE_URL}`     | `thumbnail`              | Featured image URL (used by the `image` template) |
| `{SEARCH_HIT_IMAGE_ALT}`     | `title`                  | Alt text for the featured image                   |
| `{SEARCH_HIT_VALID_THROUGH}` | `validThrough`           | Job-posting expiry date (used by `jobposting`)    |

When a hit is determined to be external, `{SEARCH_HIT_HEADING}` automatically appends an external-link icon (`fa-up-right-from-square`) after the title text. No template changes are needed.

External detection is backward-compatible:

- If the document contains `is_external` (or `isExternal` / `external`), that explicit value is used.
- Otherwise the frontend falls back to comparing the hit URL origin with `window.location.origin`.

##### Custom tokens via `placeholderMappings` filter

You can map additional token names to any field in the Typesense document:

```php
add_filter(
    'Municipio/TypesenseSearch/placeholderMappings',
    function (array $mappings): array {
        // {SEARCH_HIT_DEPARTMENT} will be replaced with the value of the
        // 'department' field on each Typesense hit document.
        $mappings['SEARCH_HIT_DEPARTMENT'] = 'department';
        return $mappings;
    }
);
```

You can then use `{SEARCH_HIT_DEPARTMENT}` freely in any custom template.

#### Optional rows: `data-js-hide-if-empty`

After all placeholders are replaced, the front-end strips any element that has the attribute `data-js-hide-if-empty` when its **text content** is empty (after trimming). Use this when optional index fields might be missing, so you do not show orphan icons or empty meta lines.

- The check uses `textContent`, so `<i aria-hidden="true">` and similar decorative nodes do not count as visible text.
- Put the attribute on the wrapper that should disappear as a whole (for example one `<span>` around icon plus label).

Example:

```blade
<span class="c-typography c-typography__variant--meta" data-js-hide-if-empty>
    <i class="{SEARCH_HIT_PLACE_ICON}" aria-hidden="true"></i>
    {SEARCH_HIT_PLACE}
</span>
```

#### Mapping post types to templates

By default all posts use the `default` template. Use the `postTypeToTemplate` filter to route specific post types to a different built-in or custom template:

```php
add_filter(
    'Municipio/TypesenseSearch/postTypeToTemplate',
    function (array $mapping): array {
        $mapping['page']        = 'noimage';      // built-in
        $mapping['product']     = 'image';        // built-in
        $mapping['job_listing'] = 'jobposting';   // built-in
        $mapping['event']       = 'my-event';     // custom (see below)
        return $mapping;
    }
);
```

#### Adding a custom template

**Step 1 — Register the template key**

```php
add_filter(
    'Municipio/TypesenseSearch/hitTemplates',
    function (array $templates): array {
        $templates[] = 'my-event';
        return $templates;
    }
);
```

**Step 2 — Point the key to a Blade view**

The default view path for a custom key `foo` is `templates.hits.foo`, which resolves to `views/templates/hits/foo.blade.php` relative to each registered view path. You can override the path for any key using the `hitTemplateView` filter:

```php
add_filter(
    'Municipio/TypesenseSearch/hitTemplateView',
    function (string $view, string $key): string {
        if ($key === 'my-event') {
            // Point to a view inside your theme or another plugin
            return 'my-theme.search.hit-event';
        }
        return $view;
    },
    10,
    2
);
```

**Step 3 — Create the Blade file**

The file must render a `<template>` element with a `data-js-search-hit-template-{key}` attribute so the front-end can find it. Use the Municipio `@element` directive or plain HTML:

```blade
@element([
    'componentElement' => 'template',
    'attributeList' => ['data-js-search-hit-template-my-event' => true]
])
    <a class="c-card c-card--action" href="{SEARCH_HIT_LINK}" aria-label="{SEARCH_HIT_ARIA_LABEL}">
        <div class="c-card__body">
            <h2>{SEARCH_HIT_HEADING}</h2>
            <p>{SEARCH_HIT_EXCERPT}</p>
            {{-- Custom token mapped via placeholderMappings --}}
            <span>{SEARCH_HIT_DEPARTMENT}</span>
        </div>
    </a>
@endelement
```

**Step 4 — Route the post type to the new template**

```php
add_filter(
    'Municipio/TypesenseSearch/postTypeToTemplate',
    function (array $mapping): array {
        $mapping['event'] = 'my-event';
        return $mapping;
    }
);
```

---

## 9. WordPress hooks and filters reference

### Actions

| Hook                                           | Parameters                                                                                                                 | When                                                                                                                                                                                                                    |
| ---------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Municipio/TypesenseSearch/RegisterStrategies` | `IndexingRegistry $registry, TypesenseClientService $clientService, SettingsRepository $settings, LoggerInterface $logger` | After built-in strategies are registered, before `IndexingHooks` is constructed. Use to register custom WP or external strategies. Accept all 4 args: `add_action(..., ..., 10, 4)`                                     |
| `typesense_search/index_post`                  | `int $post_id`                                                                                                             | Trigger (re-)indexing of a single published post from any external plugin or theme. Runs the full `shouldIndex()` → `index()` / `deindex()` logic. No-op if the post is not published or no strategy supports its type. |
| `typesense_search/deindex_post`                | `int $post_id`                                                                                                             | Remove a single post's document from the Typesense index from any external plugin or theme. Safe to call even if the document does not exist.                                                                           |

#### External triggering — usage examples

```php
// Re-index a post after your plugin changes data that should update the index.
// The post must already be published — nothing happens for drafts etc.
do_action('typesense_search/index_post', $post_id);

// Explicitly remove a post from the index.
do_action('typesense_search/deindex_post', $post_id);
```

Both hooks are wired in `IndexingHooks` during bootstrap, so they are available as soon as the plugin is active. Because `do_action()` on an unregistered hook is a silent no-op, calls made before the plugin loads are safe.

### Filters

#### Indexing filters

| Hook                                                                | Parameters                            | Purpose                                                                                             |
| ------------------------------------------------------------------- | ------------------------------------- | --------------------------------------------------------------------------------------------------- |
| `Municipio/TypesenseSearch/Indexer/shouldIndex`                     | `bool $result, WP_Post $post`         | Override `PostIndexingStrategy::shouldIndex()` for any post                                         |
| `Municipio/TypesenseSearch/DocumentBuilder/build`                   | `array $document, WP_Post $post`      | Add or transform fields on every indexed post                                                       |
| `Municipio/TypesenseSearch/DocumentBuilder/{post_type}/build`       | `array $document, WP_Post $post`      | Add or transform fields for a specific post type (replace `{post_type}` with the slug, e.g. `page`) |
| `Municipio/TypesenseSearch/PdfAttachmentAdapter/max_content_length` | `int $maxLength, WP_Post $attachment` | Override the 50 000-character PDF content cap                                                       |

#### Hit template filters

| Hook                                            | Parameters                       | Purpose                                                                                                                              |
| ----------------------------------------------- | -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| `Municipio/TypesenseSearch/hitTemplates`        | `string[] $templates`            | Add or remove template keys rendered on the search page (e.g. `['default', 'image', 'my-event']`)                                    |
| `Municipio/TypesenseSearch/hitTemplateView`     | `string $view, string $key`      | Override the Blade view path for a given template key (e.g. map `'my-event'` to `'my-theme.search.hit-event'`)                       |
| `Municipio/TypesenseSearch/postTypeToTemplate`  | `array<string,string> $mapping`  | Map Typesense `post_type` values to template keys. Entries not listed fall back to `'default'`                                       |
| `Municipio/TypesenseSearch/placeholderMappings` | `array<string,string> $mappings` | Add custom `{TOKEN}` → document field mappings that the front-end JavaScript uses when rendering hit cards (see §8.4 for an example) |
