# Typesense Search Feature Roadmap

Reviewed against local revision `10c5d6a` on 2026-09-07. Both features below
remain **unimplemented proposals**, not commitments or ready-to-build specs.
Pinned results and synonyms already exist; they do not implement these features.
The `typesense_search_notices` string in SearchStatisticsActions is an admin
notice group, not a search-notices feature or table.

[Multisite network mode](multisite-network-mode-plan.md) is now implemented
and locally verified; complete its rollout before adding these features. Each future feature must respect the resulting site-use
policy and keep data, permissions and caches scoped to the current site.
No new runtime features are introduced by this roadmap review.

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

Proposed: one row per notice in a new `$wpdb->prefix` +
`typesense_search_notices` table. Match existing database conventions with
validated JSON encoded in longtext rather than requiring a native JSON type:

| column        | type                      | notes                                  |
|---------------|---------------------------|----------------------------------------|
| id            | bigint PK AUTO_INCREMENT  |                                        |
| type          | varchar(20)   |                                        |
| title         | varchar(191)              |                                        |
| body          | text (nullable)           | infobox type only                      |
| links         | longtext (nullable)           | `[{"label":"...","url":"..."}]`        |
| trigger_terms | longtext                  | array of normalized terms that match   |
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
                          POST .../notices; PUT/DELETE .../notices/{id}
                          (manage_options + REST authentication/nonce)
source/php/Admin/SearchNoticesPage.php  menu and assets, like PinnedResultsPage

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
enough. Ignore stale responses after the query changes and clear the notice
when the query is cleared. A failed notice request must not hide search results.

Validate notice type, terms, link URLs and text lengths on write. Define an
allowed body-markup policy and escape rendered content. The public endpoint
returns only active matching notices, never administrative data. Scope caching
to site/query and invalidate it on edits. Include table migration/uninstall and
an explicit feature toggle in the implementation.

### Open decisions

- **Multiple terms per notice vs. one row per term** — JSON array chosen
  above; revisit if lookup performance becomes a concern.
- **Partial / prefix matching** — initial implementation is exact
  (normalized) match only. Glob or prefix rules can be added later.
- **Placement/order** — decide whether v1 covers only full search or also quick
  search, and define ordering when several notices match.
- **Typesense sync** — notices live only in WordPress; no Typesense side
  needed (unlike pinned results which map to curation sets).

---

## Feature B: "Did you mean?" suggestions

### Goal

When a search yields zero or very few results, show the visitor a suggested
alternative query: "Did you mean: *söka parkering*?" The suggestion is drawn
from an explicitly approved set of successful queries. The existing search
log can inform an editor's candidate selection, but must not automatically
become a public suggestion dictionary: it can contain personal text, unsuitable
queries or deliberately submitted terms. Candidate approval/storage is a product
and implementation decision required before this feature can be built.

### How the suggestion is found

1. When enabled, the frontend detects a completed search with a hit count
   less than or equal to the configured threshold (default 0: zero hits only).
2. It calls the endpoint with a bounded query; stale responses are ignored.
3. The endpoint uses a cached, site-specific approved candidate pool. If
   statistics inform ranking, aggregate by normalized query and successful
   sessions (`last_found > 0`); a log row is not a unique query. Define freshness
   and approval rules before selecting a representative display phrase.
4. A pure suggestion engine compares candidates, excluding the original query,
   and returns a match only above the agreed threshold. Algorithm selection is
   provisional; test Swedish Unicode strings and realistic typo examples.
5. Render “Menade du: …?” as an escaped, clickable suggestion that replaces the
   query. Failure or an empty pool leaves the normal result UI unchanged.

### REST endpoint

```
GET /typesense-search/v1/suggest?q=<query>
```

Public (no authentication). Returns:

```json
{ "suggestion": "söka parkering" }
```

or `204 No Content` when no good suggestion is found.

A candidate limit (proposed maximum 500) bounds comparisons; it is **not**
request rate limiting and does not guarantee a bounded database scan. Aggregated
GROUP BY/ORDER BY queries can still read many rows despite LIMIT. Inspect the
query plan on representative data and cache/precompute the pool instead of
aggregating the event log for every visitor request. Add explicit public-request
limits, query-length validation and cache invalidation on approval changes,
log deletion/retention and feature disablement where those affect the pool.
Never return raw log rows, session identifiers or statistics through this route.

### Architecture

The engine/controller could initially live inside `SearchStatistics/`.
Reassess ownership when the approved-dictionary model is decided; the sketch
below does not include its storage or administration:

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

Four proposed options (under the existing "Advanced settings" tab, or a dedicated
sub-section):

| option key                                  | default | description                                  |
|---------------------------------------------|---------|----------------------------------------------|
| `typesense_search_suggestion_enabled`        | 0       | master switch                                |
| `typesense_search_suggestion_threshold`      | 0       | max hits before a suggestion is offered      |
| `typesense_search_suggestion_min_similarity` | 60      | minimum score (0–100); algorithm pending         |
| `typesense_search_suggestion_candidate_pool` | 500     | maximum approved unique candidates compared   |

### Open decisions

- **Candidate publication** — decide who approves suggestions and where that
  approved dictionary is stored. Minimum frequency alone does not guarantee
  that a term is suitable for publication. Include this administration work in
  the estimate; the feature is larger than two engine/controller classes.
- **Similarity algorithm** — `similar_text()` and `levenshtein()` are candidates,
  not a settled choice. Benchmark bounded inputs and test multibyte Swedish
  text; do not label either a fast Unicode-aware solution without verification.
- **Candidate freshness** — past successful searches do not prove that results
  still exist. Define revalidation/expiry when content or the index changes.
- **When logging is disabled** — for a log-dependent v1, return 204 and do not
  initiate frontend requests. If an independent approved dictionary is chosen,
  explicitly decide whether it should continue working without logging.
- **Normalization** — Repository::normalizeQuery() currently collapses
  whitespace, optionally applies Unicode NFC, and lowercases (multibyte when
  available). It does **not** strip accents or equate å/ä/ö with a/o. Preserve
  that behavior unless an explicit language policy and tests justify a change.
- **Existing search behavior** — compare the proposed experience with current
  typo tolerance and synonym behavior before committing to a separate service.

## Verification required for either feature

Test site isolation, feature disablement, unauthorized writes, safe output,
empty/no-match states, stale frontend responses and failures of the extra
request. For suggestions also test candidate approval, cache invalidation,
Unicode quality and representative query cost. These are future implementation
checks; no feature tests or live changes were run during this document audit.
