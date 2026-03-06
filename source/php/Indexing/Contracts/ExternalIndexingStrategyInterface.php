<?php

namespace TypesenseSearch\Indexing\Contracts;

/**
 * Interface ExternalIndexingStrategyInterface
 *
 * Defines the contract for indexing strategies that pull content from external
 * sources (e.g. e-service APIs, open-data feeds, third-party CMS exports) that
 * have no corresponding WordPress post.
 *
 * ── How this differs from IndexingStrategyInterface ───────────────────────
 *
 * IndexingStrategyInterface is event-driven: it reacts to WordPress post
 * lifecycle hooks (save, trash, delete). External strategies are pull-driven:
 * content is fetched on demand from a remote source and pushed into Typesense.
 * There is no \WP_Post involved at any stage.
 *
 * ── Document IDs ──────────────────────────────────────────────────────────
 *
 * External documents share the same Typesense collection as WordPress content.
 * To avoid ID collisions with WordPress post IDs (which are plain integers),
 * every external strategy MUST namespace its document IDs, for example:
 *
 *   'id' => 'eservice-' . $item['id']
 *
 * ── Triggering a sync ─────────────────────────────────────────────────────
 *
 * Syncs are triggered by:
 *   - A WP-Cron event scheduled in registerHooks() (most common).
 *   - A WP-CLI command that calls IndexingRegistry::runExternalSync().
 *   - Any other custom trigger (webhook, admin action, etc.).
 *
 * ── Implementing a strategy ───────────────────────────────────────────────
 *
 * Extend AbstractExternalIndexingStrategy rather than implementing this
 * interface directly. The abstract class provides syncAll() and deindex() so
 * you only need to implement:
 *
 *   getIdentifier() — unique slug, e.g. 'eservice'
 *   fetchItems()    — return an iterable of raw items from the source
 *   buildDocument() — turn one raw item into an IndexableDocument
 *   getExternalId() — return the namespaced string ID for one raw item
 *
 * See EServiceIndexingStrategy for a complete worked example.
 *
 * @package TypesenseSearch\Indexing\Contracts
 */
interface ExternalIndexingStrategyInterface
{
    /**
     * Return a unique slug for this strategy (e.g. 'eservice', 'news-feed').
     *
     * Used by IndexingRegistry to look up strategies by name and in log
     * messages.
     *
     * @return string
     */
    public function getIdentifier(): string;

    /**
     * Fetch all items from the external source, build Typesense documents,
     * and upsert them into the collection.
     *
     * Returns the number of documents successfully upserted. Implementations
     * should log and skip individual failures rather than aborting the entire
     * run, so a partial sync is possible.
     *
     * @return int Number of successfully indexed items.
     */
    public function syncAll(): int;

    /**
     * Remove a single document from the Typesense index by its external ID.
     *
     * The $externalId is the namespaced string used as the Typesense document
     * 'id' field (e.g. 'eservice-42'). Implementations should treat
     * "document not found" as success so callers need not check existence.
     *
     * @param string $externalId Namespaced Typesense document ID.
     * @return bool True on success (or already absent), false on error.
     */
    public function deindex(string $externalId): bool;

    /**
     * Register any WordPress hooks this strategy needs.
     *
     * Called once during bootstrap via IndexingRegistry::registerAllHooks().
     * Use this to schedule WP-Cron events or register admin actions that
     * trigger a sync.
     *
     * Example — schedule a daily sync:
     *
     *   add_action('init', function () {
     *       if (!wp_next_scheduled('typesense_sync_eservice')) {
     *           wp_schedule_event(time(), 'daily', 'typesense_sync_eservice');
     *       }
     *   });
     *   add_action('typesense_sync_eservice', [$this, 'syncAll']);
     *
     * @return void
     */
    public function registerHooks(): void;
}
