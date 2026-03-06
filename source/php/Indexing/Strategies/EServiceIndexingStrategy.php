<?php

namespace TypesenseSearch\Indexing\Strategies;

use TypesenseSearch\Indexing\IndexableDocument;

/**
 * Class EServiceIndexingStrategy
 *
 * Example external indexing strategy that indexes e-services from a JSON
 * REST API endpoint into Typesense. This class is intended as a reference
 * implementation — copy and adapt it for your own external source.
 *
 * ── How to register this strategy ────────────────────────────────────────
 *
 * Register it alongside your WordPress strategies during bootstrap
 * (typically in App::__construct or a service provider):
 *
 *   $registry->registerExternal(new EServiceIndexingStrategy());
 *
 * IndexingRegistry::registerAllHooks() will then call registerHooks() so
 * the daily cron event is scheduled automatically.
 *
 * ── How syncing works ─────────────────────────────────────────────────────
 *
 * 1. WP-Cron fires the 'typesense_sync_eservice' event daily.
 * 2. This calls syncAll(), which fetches all e-services from the API,
 *    builds an IndexableDocument for each one, and upserts them.
 * 3. Individual failures are logged and skipped; the rest of the batch
 *    continues.
 *
 * To trigger a sync manually (e.g. from WP-CLI):
 *
 *   $strategy = $registry->getExternal('eservice');
 *   $count    = $strategy->syncAll();
 *
 * To remove a single e-service from the index (use the namespaced ID):
 *
 *   $strategy->deindex('eservice-42');
 *
 * ── Document ID namespacing ───────────────────────────────────────────────
 *
 * External documents share the same Typesense collection as WordPress posts.
 * To prevent ID collisions, every document ID is prefixed with the strategy
 * identifier: 'eservice-{id}'.  This is a hard requirement — plain integer
 * IDs would clash with WordPress post IDs.
 *
 * @package TypesenseSearch\Indexing\Strategies
 */
class EServiceIndexingStrategy extends AbstractExternalIndexingStrategy
{
    /**
     * WP-Cron hook name used to trigger the daily sync.
     * Reuse this constant when deregistering the event on plugin uninstall.
     */
    public const CRON_HOOK = 'typesense_sync_eservice';

    /**
     * URL of the remote API that returns e-services as JSON.
     * In a real implementation, expose this via plugin settings.
     */
    private string $apiUrl;

    public function __construct(string $apiUrl = 'https://example.com/api/eservices')
    {
        $this->apiUrl = $apiUrl;
    }

    // ── ExternalIndexingStrategyInterface ──────────────────────────────────

    /**
     * {@inheritdoc}
     */
    public function getIdentifier(): string
    {
        return 'eservice';
    }

    /**
     * Schedule a daily WP-Cron event that calls syncAll().
     *
     * Also registers the action that executes when the event fires.
     * Safe to call on every request — wp_next_scheduled() prevents
     * double-scheduling.
     *
     * {@inheritdoc}
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

    // ── AbstractExternalIndexingStrategy ───────────────────────────────────

    /**
     * Fetch all e-services from the remote API.
     *
     * Returns an empty array on network or parse failure and logs the error so
     * syncAll() exits cleanly (0 items indexed) rather than throwing.
     *
     * {@inheritdoc}
     */
    protected function fetchItems(): iterable
    {
        $response = wp_remote_get($this->apiUrl, ['timeout' => 15]);

        if (is_wp_error($response)) {
            error_log(sprintf(
                '[TypesenseSearch][eservice] API request failed: %s',
                $response->get_error_message()
            ));
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data)) {
            error_log('[TypesenseSearch][eservice] API returned invalid JSON.');
            return [];
        }

        // Assume the API returns either a flat array of items or an object with
        // an 'items' key — adjust for your actual API shape.
        return $data['items'] ?? $data;
    }

    /**
     * Build a Typesense document from one e-service item.
     *
     * Returns false when required fields ('id', 'title') are absent so the
     * item is skipped rather than causing an exception.
     *
     * {@inheritdoc}
     */
    protected function buildDocument(mixed $item): IndexableDocument|false
    {
        if (empty($item['id']) || empty($item['title'])) {
            return false;
        }

        return new IndexableDocument([
            'id'        => $this->getExternalId($item),    // namespaced
            'title'     => (string) $item['title'],
            'content'   => (string) ($item['description'] ?? ''),
            'excerpt'   => (string) ($item['short_description'] ?? $item['description'] ?? ''),
            'url'       => (string) ($item['url'] ?? ''),
            'type'      => 'eservice',
            'type_name' => __('E-service', 'typesense-search'),
            'date'      => isset($item['updated_at'])
                               ? (int) strtotime((string) $item['updated_at'])
                               : 0,
        ]);
    }

    /**
     * Return the namespaced document ID for a raw e-service item.
     *
     * Format: 'eservice-{id}' — matches the 'id' field set in buildDocument().
     *
     * {@inheritdoc}
     */
    protected function getExternalId(mixed $item): string
    {
        return 'eservice-' . $item['id'];
    }
}
