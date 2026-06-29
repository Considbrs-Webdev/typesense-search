# Typesense Search Feature Roadmap

Planned features that extend the plugin beyond its current search and indexing
scope. Each section describes the goal, the data model, the architectural
shape, and the open decisions that need to be resolved before implementation.

---

## Feature A: Search notices (infoboxes and links above results)

### Goal

Editors define rules that match specific search terms. When a visitor searches
for a matching term, a notice is rendered above the search results. A notice
can be an infobox (title + body text + optional CTA link) or a list of
curated links.

Examples of what this enables:

- Search for "parkering" → infobox with current rules and a link to the
  parking permit form.
- Search for "kontakt" → a set of quick links to department contact pages.

### Data model

One row per notice in a new `typesense_search_notices` table:

| column        | type                      | notes                                  |
|---------------|---------------------------|----------------------------------------|
| id            | bigint PK AUTO_INCREMENT  |                                        |
| type          | enum('infobox','links')   |                                        |
| title         | varchar(191)              |                                        |
| body          | text (nullable)           | infobox type only                      |
| links         | JSON (nullable)           | `[{"label":"...","url":"..."}]`        |
| trigger_terms | JSON                      | array of normalized terms that match   |
| active        | tinyint(1) DEFAULT 1      |                                        |
| created_at    | datetime                  |                                        |
| updated_at    | datetime                  |                                        |

`trigger_terms` is stored as a JSON array of normalized strings (same
normalization as `SearchStatistics\Repository::normalizeQuery`). Lookup at
query time fetches all active notices and filters in PHP — suitable as long as
the number of notices stays in the tens to low hundreds.

### Architecture

Follows the same structure as `PinnedResults/`:

```
source/php/SearchNotices/
    Database.php          table definition and migrations
    Repository.php        CRUD; lookup by normalized query
    RestController.php    GET /typesense-search/v1/notices?q=... (public, nonce-free)
                          POST/PUT/DELETE .../notices/{id} (manage_options)
    AdminPage.php         registers menu item and enqueues assets

source/js/search-notices/
    types.ts
    state.ts
    api.ts
    render.ts
    events.ts
source/js/search-notices-admin.ts   thin entry
```

Bootstrap wiring goes in a new `Bootstrap/SearchNoticesFeature.php` following
the same pattern as `PinnedResultsFeature`.

### Frontend integration

After a search resolves, the frontend calls the REST endpoint with the
current query. If a matching notice is returned, it is rendered above the
result list. The call should be debounced with the search itself to avoid
an extra round-trip on every keystroke — one call per completed search is
enough.

### Open decisions

- **Multiple terms per notice vs. one row per term** — JSON array chosen
  above; revisit if lookup performance becomes a concern.
- **Partial / prefix matching** — initial implementation is exact
  (normalized) match only. Glob or prefix rules can be added later.
- **Typesense sync** — notices live only in WordPress; no Typesense side
  needed (unlike pinned results which map to curation sets).

---

## Feature B: "Did you mean?" suggestions

### Goal

When a search yields zero or very few results, show the visitor a suggested
alternative query: "Did you mean: *söka parkering*?" The suggestion is drawn
from queries that other visitors have searched for successfully, using the
existing search log as the data source.

### How the suggestion is found

1. The frontend detects that a completed search returned fewer than N hits
   (threshold configurable, default: 0, i.e. only on true zero-hit results).
2. It calls a new REST endpoint with the failing query.
3. The endpoint fetches the top-K most-searched unique queries from the log
   that have `last_found > 0` (candidates with actual results).
4. It computes string similarity between the failing query and each candidate
   using PHP `similar_text()` (fast, no extension required). The candidate
   with the highest similarity score above a minimum threshold is returned.
5. The frontend renders "Menade du: *<suggestion>*?" as a clickable link
   that replaces the current query.

### REST endpoint

```
GET /typesense-search/v1/suggest?q=<query>
```

Public (no authentication). Returns:

```json
{ "suggestion": "söka parkering" }
```

or `204 No Content` when no good suggestion is found.

The endpoint is rate-limited by the candidate pool size in PHP — it reads at
most a configurable number of rows (default 500 most-searched terms) and
never performs a full table scan.

### Architecture

The feature is small enough to live inside `SearchStatistics/` rather than
warranting its own top-level namespace:

```
source/php/SearchStatistics/
    SuggestionEngine.php   pure class: takes a query + candidate array,
                           returns best match or null
    SuggestionController.php  REST endpoint wired to the engine
```

`SuggestionEngine` has no WordPress dependencies and is straightforward
to unit-test.

Bootstrap wiring: a new `registerSuggestionEndpoint()` call inside
`Bootstrap/SearchStatisticsFeature.php`, or a new
`Bootstrap/SuggestionFeature.php` if the engine grows.

### Settings

Two new options (under the existing "Advanced settings" tab, or a dedicated
sub-section):

| option key                                  | default | description                                  |
|---------------------------------------------|---------|----------------------------------------------|
| `typesense_search_suggestion_enabled`        | 0       | master switch                                |
| `typesense_search_suggestion_threshold`      | 0       | max hits before a suggestion is offered      |
| `typesense_search_suggestion_min_similarity` | 60      | minimum `similar_text` score (0–100)         |
| `typesense_search_suggestion_candidate_pool` | 500     | how many log rows are pulled as candidates   |

### Open decisions

- **Similarity algorithm** — `similar_text()` is simple and requires no
  extension. `levenshtein()` is an alternative that may handle short queries
  and typos more accurately but has O(n·m) cost. Can be swapped inside
  `SuggestionEngine` without changing anything else.
- **Candidate freshness** — the pool could be filtered to queries seen within
  the last N days (reusing the retention window) to avoid surfacing stale
  suggestions.
- **When search logging is disabled** — the endpoint returns 204 immediately;
  no suggestion is possible without log data. The frontend should handle this
  gracefully and not show any UI element.
- **Language / Swedish-specific normalization** — both the failing query and
  the candidates pass through `Repository::normalizeQuery()` before comparison
  so diacritic and case differences are already handled.
