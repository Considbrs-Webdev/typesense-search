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
5. [Per-post controls](#5-per-post-controls)
6. [WP-CLI commands](#6-wp-cli-commands)
7. [How indexing works](#7-how-indexing-works)
8. [Extensibility](#8-extensibility)
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

### Connection tab

These settings tell the plugin how to reach your Typesense instance.

| Setting                 | Option key                       | Description                                                                       |
| ----------------------- | -------------------------------- | --------------------------------------------------------------------------------- |
| Remote URL              | `typesense_search_remote`        | Base URL of your Typesense server, e.g. `https://search.example.com`              |
| Index (collection) name | `typesense_search_index_name`    | The Typesense collection to read from and write to                                |
| Admin API key           | `typesense_search_admin_key`     | Full-access key — used server-side for indexing and collection management         |
| Search API key          | `typesense_search_search_key`    | Read-only key — passed to the front-end JavaScript                                |
| Frontend host           | `typesense_search_frontend_host` | Optional override of the host sent to the browser (useful behind reverse proxies) |

The admin key is kept server-side. The search key is the only credential exposed to the browser.

### Settings tab (content)

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

### Facetting tab

Configure which fields can be used as facets in the search UI.

| Setting | Option key                | Description                                                                                                                                                |
| ------- | ------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Facets  | `typesense_search_facets` | Array of facet definitions. Each entry has: `field` (Typesense field name), `label` (UI label), `placeholder`, `display_as` (`dropdown` or `button_group`) |

### Quick search tab

Quick search is a lightweight search overlay that attaches to any element on the page.

| Setting             | Option key                             | Description                                                                                                                                                 |
| ------------------- | -------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Enable quick search | `typesense_quick_search_enabled`       | Toggle the feature on or off                                                                                                                                |
| CSS selectors       | `typesense_quick_search_selectors`     | One or more CSS selectors the overlay binds to. Each entry has `selector` and `sibling` (bool — place the widget next to the element rather than inside it) |
| Results per page    | `typesense_quick_search_hits_per_page` | Number of results shown in the overlay (default: 5)                                                                                                         |

### Statistics tab

Read-only overview of the Typesense collection: document count and index size. Uses the admin API key to query Typesense directly from the browser via an AJAX proxy.

### Status tab

Checks whether the current configuration is valid and the collection exists. Can create the collection if it is missing.

---

## 5. Per-post controls

Every indexed post type records two meta fields, managed via a meta box visible in the post editor.

| Meta key                 | Constant                    | Effect                                                                                                                             |
| ------------------------ | --------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| `_typesense_exclude`     | `MetaBox::META_EXCLUDE`     | Set to `'1'` to prevent this post from being indexed (or to remove it from the index if already present)                           |
| `_typesense_extra_terms` | `MetaBox::META_EXTRA_TERMS` | Free-text field included in the indexed document, allowing keywords that don't appear in the post body to influence search ranking |

The `_typesense_exclude` flag is honoured by all built-in strategies. Custom strategies should check it in their `shouldIndex()` implementation if the same per-post control is desired.

---

## 6. WP-CLI commands

The plugin registers a `typesense` command when WP-CLI is loaded.

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

# Slow down the progress bar for visual debugging
wp typesense index --dry-run --sleep=200
```

| Flag                  | Description                                                                |
| --------------------- | -------------------------------------------------------------------------- |
| `--post-type=<types>` | Comma-separated post-type slugs. Defaults to all types enabled in settings |
| `--batch-size=<n>`    | Posts per database query. Defaults to all posts in one query               |
| `--dry-run`           | Resolve strategies and check `shouldIndex()` but do not write to Typesense |
| `--include-pdf`       | Also index PDF attachments via the PDF strategy                            |
| `--yes`               | Skip the confirmation prompt                                               |
| `--sleep=<ms>`        | Sleep after each post (useful for development)                             |

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
```

### 7.2 IndexingHooks

`IndexingHooks` wires three WordPress actions to the registry during bootstrap.

| WordPress hook                       | When it fires                                     | What the plugin does                                                                                                                                       |
| ------------------------------------ | ------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `wp_after_insert_post` (priority 20) | After a post **and all its meta** are fully saved | If `post_status === 'publish'`: call `shouldIndex()` → `index()` (or `deindex()` if excluded). If transitioning **away** from `publish`: call `deindex()`. |
| `trashed_post`                       | Post moved to the Trash                           | `deindex()`                                                                                                                                                |
| `before_delete_post`                 | Post permanently deleted                          | `deindex()`                                                                                                                                                |

Priority 20 on `wp_after_insert_post` is intentional — it ensures all meta boxes have written their values before `shouldIndex()` reads them.

PDF attachments have their own lifecycle hooks (`add_attachment`, `edit_attachment`, `delete_attachment`) registered by `PdfIndexingStrategy::registerHooks()`.

### 7.3 IndexingRegistry

The registry is the central routing table. It holds two separate sets of strategies:

- **WordPress strategies** (`IndexingStrategyInterface`) — event-driven; one handles each post saved by WordPress.
- **External strategies** (`ExternalIndexingStrategyInterface`) — pull-driven; triggered explicitly by cron, CLI, or any other mechanism. See §7.6 and §8.3.

Built-in registration order (order matters — first match wins for WordPress strategies):

1. `PdfIndexingStrategy` — matches PDF attachments
2. `PostIndexingStrategy` — matches everything else that is not an attachment

### 7.4 IndexingStrategyInterface

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

### 7.5 IndexableDocument

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

### 7.6 Built-in strategies

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

### 7.7 DocumentBuilder and the filter chain

`DocumentBuilder::build()` assembles the document array for a WordPress post and passes it through two WordPress filter layers before wrapping it in `IndexableDocument`.

| Filter hook                                                   | Receives                           | Fires                           |
| ------------------------------------------------------------- | ---------------------------------- | ------------------------------- |
| `Municipio/TypesenseSearch/DocumentBuilder/build`             | `(array $document, WP_Post $post)` | Every post, regardless of type  |
| `Municipio/TypesenseSearch/DocumentBuilder/{post_type}/build` | `(array $document, WP_Post $post)` | Only posts of the matching type |

Both filters receive and must return a plain `array`. `IndexableDocument` is created after all filters have run.

### 7.8 Enrichers

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

#### Step 2 — Register via the action hook

```php
add_action(
    'Municipio/TypesenseSearch/RegisterStrategies',
    function (\TypesenseSearch\Indexing\IndexingRegistry $registry): void {
        // Register before PostIndexingStrategy if your type could otherwise
        // be caught by the generic post handler first.
        $registry->register(new \MyPlugin\Search\ProductIndexingStrategy());
    }
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
            error_log('[EService] API error: ' . $response->get_error_message());
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

#### Step 2 — Register via the action hook

```php
add_action(
    'Municipio/TypesenseSearch/RegisterStrategies',
    function (\TypesenseSearch\Indexing\IndexingRegistry $registry): void {
        $registry->registerExternal(new \MyPlugin\Search\EServiceIndexingStrategy());
    }
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

---

## 9. WordPress hooks and filters reference

### Actions

| Hook                                           | Parameters                   | When                                                                                                                              |
| ---------------------------------------------- | ---------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `Municipio/TypesenseSearch/RegisterStrategies` | `IndexingRegistry $registry` | After built-in strategies are registered, before `IndexingHooks` is constructed. Use to register custom WP or external strategies |

### Filters

| Hook                                                                | Parameters                            | Purpose                                                                                             |
| ------------------------------------------------------------------- | ------------------------------------- | --------------------------------------------------------------------------------------------------- |
| `Municipio/TypesenseSearch/Indexer/shouldIndex`                     | `bool $result, WP_Post $post`         | Override `PostIndexingStrategy::shouldIndex()` for any post                                         |
| `Municipio/TypesenseSearch/DocumentBuilder/build`                   | `array $document, WP_Post $post`      | Add or transform fields on every indexed post                                                       |
| `Municipio/TypesenseSearch/DocumentBuilder/{post_type}/build`       | `array $document, WP_Post $post`      | Add or transform fields for a specific post type (replace `{post_type}` with the slug, e.g. `page`) |
| `Municipio/TypesenseSearch/PdfAttachmentAdapter/max_content_length` | `int $maxLength, WP_Post $attachment` | Override the 50 000-character PDF content cap                                                       |
