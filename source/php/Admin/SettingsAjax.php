<?php

namespace TypesenseSearch\Admin;

use Typesense\Exceptions\RequestUnauthorized;
use TypesenseSearch\Typesense\ApiKey;
use TypesenseSearch\Typesense\ClientFactory;
use TypesenseSearch\Typesense\Collection;

/**
 * Class SettingsAjax
 *
 * Handles the admin-ajax.php endpoints that back the Typesense settings page:
 *   1. Test the server connection and check whether the collection exists.
 *   2. Create the collection (delegated to Collection).
 *   3. Generate a scoped search-only API key (delegated to ApiKey).
 *
 * @package TypesenseSearch\Admin
 */
class SettingsAjax
{
    public const AJAX_ACTION_TEST             = 'typesense_test_connection';
    public const AJAX_ACTION_CREATE_COL       = 'typesense_create_collection';
    public const AJAX_ACTION_GEN_KEY          = 'typesense_generate_search_key';
    public const AJAX_ACTION_GET_STATS        = 'typesense_get_stats';
    public const AJAX_ACTION_CLEAR_POST_TYPE   = 'typesense_clear_post_type';
    public const AJAX_ACTION_REINDEX_POST_TYPE = 'typesense_reindex_post_type';
    public const AJAX_ACTION_GET_FACET_FIELDS = 'typesense_get_facet_fields';
    public const AJAX_ACTION_CHECK_STATUS       = 'typesense_check_status';
    public const AJAX_ACTION_FIX_SEARCH_KEY      = 'typesense_fix_search_key';
    public const AJAX_ACTION_STATUS_CREATE_COL   = 'typesense_status_create_collection';

    public function __construct()
    {
        add_action('wp_ajax_' . self::AJAX_ACTION_TEST,              [$this, 'handle']);
        add_action('wp_ajax_' . self::AJAX_ACTION_CREATE_COL,         [$this, 'handleCreateCollection']);
        add_action('wp_ajax_' . self::AJAX_ACTION_GEN_KEY,            [$this, 'handleGenerateSearchKey']);
        add_action('wp_ajax_' . self::AJAX_ACTION_GET_STATS,          [$this, 'handleGetStats']);
        add_action('wp_ajax_' . self::AJAX_ACTION_CLEAR_POST_TYPE,    [$this, 'handleClearPostType']);
        add_action('wp_ajax_' . self::AJAX_ACTION_REINDEX_POST_TYPE,  [$this, 'handleReindexPostType']);
        add_action('wp_ajax_' . self::AJAX_ACTION_GET_FACET_FIELDS,   [$this, 'handleGetFacetFields']);
        add_action('wp_ajax_' . self::AJAX_ACTION_CHECK_STATUS,       [$this, 'handleCheckStatus']);
        add_action('wp_ajax_' . self::AJAX_ACTION_FIX_SEARCH_KEY,     [$this, 'handleFixSearchKey']);
        add_action('wp_ajax_' . self::AJAX_ACTION_STATUS_CREATE_COL,  [$this, 'handleStatusCreateCollection']);
    }

    // ── Shared helpers ────────────────────────────────────────────────────────

    /**
     * Validate shared POST fields and return them, or send a JSON error and terminate.
     *
     * @return array{remote: string, adminKey: string}
     */
    private function requireConnectionFields(string $nonce): array
    {
        check_ajax_referer($nonce, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'typesense-search')], 403);
        }

        $remote   = sanitize_text_field(wp_unslash($_POST['remote'] ?? ''));
        $adminKey = sanitize_text_field(wp_unslash($_POST['admin_key'] ?? ''));

        if (empty($remote) || empty($adminKey)) {
            wp_send_json_error([
                'step'    => 'validation',
                'message' => __('Enter both a host URL and an Admin API key before testing.', 'typesense-search'),
            ]);
        }

        $parsed = parse_url($remote);
        if (!$parsed || empty($parsed['host'])) {
            wp_send_json_error([
                'step'    => 'validation',
                'message' => __('The host value is not a valid URL.', 'typesense-search'),
            ]);
        }

        return compact('remote', 'adminKey');
    }

    // ── 1. Test connection ────────────────────────────────────────────────────

    public function handle(): void
    {
        ['remote' => $remote, 'adminKey' => $adminKey] = $this->requireConnectionFields(self::AJAX_ACTION_TEST);

        // Step 1: health
        try {
            $client = ClientFactory::build($remote, $adminKey);
            $health = $client->health->retrieve();
        } catch (\Exception $e) {
            wp_send_json_error([
                'step'    => 'health',
                'message' => sprintf(
                    /* translators: %s: error message */
                    __('Could not reach server: %s', 'typesense-search'),
                    $e->getMessage()
                ),
            ]);
            return;
        }

        if (empty($health['ok'])) {
            wp_send_json_error([
                'step'    => 'health',
                'message' => __('Server responded but reported an unhealthy status.', 'typesense-search'),
            ]);
            return;
        }

        // Step 2: admin key
        try {
            $client->collections->retrieve();
        } catch (RequestUnauthorized $e) {
            wp_send_json_error([
                'step'    => 'auth',
                'message' => __('Server is reachable, but the Admin API key was rejected.', 'typesense-search'),
            ]);
            return;
        } catch (\Exception $e) {
            wp_send_json_error([
                'step'    => 'auth',
                'message' => sprintf(
                    /* translators: %s: error message */
                    __('Server is reachable, but key validation failed: %s', 'typesense-search'),
                    $e->getMessage()
                ),
            ]);
            return;
        }

        // Step 3: does the named collection exist?
        $collectionName  = sanitize_text_field(wp_unslash($_POST['collection_name'] ?? ''));
        $collectionExists = false;

        if (!empty($collectionName)) {
            $collectionExists = Collection::exists($client, $collectionName);
        }

        wp_send_json_success([
            'message'          => __('Connected successfully. Server is healthy and the Admin API key is valid.', 'typesense-search'),
            'collectionExists' => $collectionExists,
            'collectionName'   => $collectionName,
        ]);
    }

    // ── 2. Create collection ─────────────────────────────────────────────────

    public function handleCreateCollection(): void
    {
        ['remote' => $remote, 'adminKey' => $adminKey] = $this->requireConnectionFields(self::AJAX_ACTION_CREATE_COL);

        $collectionName = sanitize_text_field(wp_unslash($_POST['collection_name'] ?? ''));

        if (empty($collectionName)) {
            wp_send_json_error([
                'message' => __('Collection name is required.', 'typesense-search'),
            ]);
            return;
        }

        try {
            $client = ClientFactory::build($remote, $adminKey);
            Collection::create($client, $collectionName);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: error message */
                    __('Could not create collection: %s', 'typesense-search'),
                    $e->getMessage()
                ),
            ]);
            return;
        }

        wp_send_json_success([
            'message' => sprintf(
                /* translators: %s: collection name */
                __('Collection "%s" created successfully.', 'typesense-search'),
                $collectionName
            ),
        ]);
    }

    // ── 3. Generate scoped search key ────────────────────────────────────────

    public function handleGenerateSearchKey(): void
    {
        ['remote' => $remote, 'adminKey' => $adminKey] = $this->requireConnectionFields(self::AJAX_ACTION_GEN_KEY);

        $collectionName = sanitize_text_field(wp_unslash($_POST['collection_name'] ?? ''));

        if (empty($collectionName)) {
            wp_send_json_error([
                'message' => __('Set a collection name before generating a search key.', 'typesense-search'),
            ]);
            return;
        }

        try {
            $client = ClientFactory::build($remote, $adminKey);
            $key    = ApiKey::generateSearchKey($client, $collectionName);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: error message */
                    __('Could not generate search key: %s', 'typesense-search'),
                    $e->getMessage()
                ),
            ]);
            return;
        }

        wp_send_json_success([
            'message' => __('Search key generated. It has been filled in below — save settings to keep it.', 'typesense-search'),
            'key'     => $key,
        ]);
    }

    // ── 4. Get collection statistics ─────────────────────────────────────────

    public function handleGetStats(): void
    {
        check_ajax_referer(self::AJAX_ACTION_GET_STATS, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'typesense-search')], 403);
            return;
        }

        $remote         = (string) get_option(Settings::OPTION_REMOTE, '');
        $adminKey       = (string) get_option(Settings::OPTION_ADMIN_KEY, '');
        $collectionName = (string) get_option(Settings::OPTION_INDEX_NAME, '');

        if (empty($remote) || empty($adminKey) || empty($collectionName)) {
            wp_send_json_error(['message' => __('Connection settings are incomplete. Please configure the connection first.', 'typesense-search')]);
            return;
        }

        try {
            $client = ClientFactory::build($remote, $adminKey);

            $result = $client->collections[$collectionName]->documents->search([
                'q'                => '*',
                'query_by'         => 'title',
                'facet_by'         => 'type,type_name',
                'max_facet_values' => 100,
                'per_page'         => 0,
            ]);

            $total  = $result['found'] ?? 0;
            $facets = [];

            // Build a position-indexed map of type_name values. Because type and
            // type_name have a strict 1:1 relationship per document, Typesense
            // returns both facet groups in the same count-descending order, so
            // the N-th type entry corresponds to the N-th type_name entry.
            $typeNamesByPosition = [];
            foreach ($result['facet_counts'] ?? [] as $facetGroup) {
                if (($facetGroup['field_name'] ?? '') === 'type_name') {
                    foreach ($facetGroup['counts'] ?? [] as $i => $item) {
                        $typeNamesByPosition[$i] = $item['value'];
                    }
                    break;
                }
            }

            foreach ($result['facet_counts'] ?? [] as $facetGroup) {
                if (($facetGroup['field_name'] ?? '') === 'type') {
                    $externalIds = array_keys(\TypesenseSearch\App::getRegistry()->allExternal());

                    foreach ($facetGroup['counts'] ?? [] as $i => $item) {
                        $slug        = $item['value'];
                        // Prefer the registered WP label; fall back to the stored
                        // type_name (correct for external services), then the slug.
                        $postTypeObj = get_post_type_object($slug);
                        $label       = $postTypeObj
                            ? $postTypeObj->label
                            : ($typeNamesByPosition[$i] ?? $slug);

                        $facets[] = [
                            'slug'     => $slug,
                            'label'    => $label,
                            'count'    => (int) $item['count'],
                            'external' => in_array($slug, $externalIds, true),
                        ];
                    }
                }
            }

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: error message */
                    __('Could not load statistics: %s', 'typesense-search'),
                    $e->getMessage()
                ),
            ]);
            return;
        }

        wp_send_json_success([
            'total'          => $total,
            'collectionName' => $collectionName,
            'facets'         => $facets,
        ]);
    }

    // ── 5. Clear post type from index ────────────────────────────────────────

    public function handleClearPostType(): void
    {
        check_ajax_referer(self::AJAX_ACTION_CLEAR_POST_TYPE, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'typesense-search')], 403);
            return;
        }

        $postType = sanitize_key(wp_unslash($_POST['post_type'] ?? ''));

        if (empty($postType)) {
            wp_send_json_error(['message' => __('Post type is required.', 'typesense-search')]);
            return;
        }

        $remote         = (string) get_option(Settings::OPTION_REMOTE, '');
        $adminKey       = (string) get_option(Settings::OPTION_ADMIN_KEY, '');
        $collectionName = (string) get_option(Settings::OPTION_INDEX_NAME, '');

        if (empty($remote) || empty($adminKey) || empty($collectionName)) {
            wp_send_json_error(['message' => __('Connection settings are incomplete.', 'typesense-search')]);
            return;
        }

        try {
            $client = ClientFactory::build($remote, $adminKey);
            $result = $client->collections[$collectionName]->documents->delete([
                'filter_by' => 'type:=' . $postType,
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: error message */
                    __('Could not clear post type: %s', 'typesense-search'),
                    $e->getMessage()
                ),
            ]);
            return;
        }

        wp_send_json_success([
            'message' => sprintf(
                /* translators: 1: number of deleted docs, 2: post type slug */
                __('Removed %1$d documents of type "%2$s" from the index.', 'typesense-search'),
                $result['num_deleted'] ?? 0,
                $postType
            ),
            'deleted'  => $result['num_deleted'] ?? 0,
            'postType' => $postType,
        ]);
    }

    // ── 6. Reindex post type ─────────────────────────────────────────────────

    public function handleReindexPostType(): void
    {
        check_ajax_referer(self::AJAX_ACTION_REINDEX_POST_TYPE, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'typesense-search')], 403);
            return;
        }

        $postType = sanitize_key(wp_unslash($_POST['post_type'] ?? ''));

        if (empty($postType)) {
            wp_send_json_error(['message' => __('Post type is required.', 'typesense-search')]);
            return;
        }

        // Allow the indexing loop to run to completion regardless of request
        // timeout or client disconnect — important for large sites.
        ignore_user_abort(true);
        set_time_limit(0);

        $indexed  = 0;
        $skipped  = 0;
        $failed   = 0;
        $registry = \TypesenseSearch\App::getRegistry();

        try {
            if ($postType === 'attachment') {
                // PDF attachment reindex
                $strategy = $registry->get('pdf');

                if ($strategy === null) {
                    wp_send_json_error(['message' => __('PDF indexing strategy is not available.', 'typesense-search')]);
                    return;
                }

                $offset    = 0;
                $batchSize = 50;

                do {
                    $posts = get_posts([
                        'post_type'        => 'attachment',
                        'post_status'      => 'inherit',
                        'post_mime_type'   => 'application/pdf',
                        'posts_per_page'   => $batchSize,
                        'offset'           => $offset,
                        'orderby'          => 'ID',
                        'order'            => 'ASC',
                        'suppress_filters' => false,
                    ]);

                    foreach ($posts as $post) {
                        if (!$strategy->shouldIndex($post)) {
                            $skipped++;
                            continue;
                        }

                        $strategy->index($post) ? $indexed++ : $failed++;
                    }

                    $offset += $batchSize;
                } while (count($posts) === $batchSize);
            } else {
                // Standard published post type reindex
                $offset    = 0;
                $batchSize = 50;

                do {
                    $posts = get_posts([
                        'post_type'        => $postType,
                        'post_status'      => 'publish',
                        'posts_per_page'   => $batchSize,
                        'offset'           => $offset,
                        'orderby'          => 'ID',
                        'order'            => 'ASC',
                        'suppress_filters' => false,
                    ]);

                    foreach ($posts as $post) {
                        $strategy = $registry->resolve($post);

                        if (!$strategy || !$strategy->shouldIndex($post)) {
                            $skipped++;
                            continue;
                        }

                        $strategy->index($post) ? $indexed++ : $failed++;
                    }

                    $offset += $batchSize;
                } while (count($posts) === $batchSize);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: error message */
                    __('Reindex failed: %s', 'typesense-search'),
                    $e->getMessage()
                ),
            ]);
            return;
        }

        wp_send_json_success([
            /* translators: 1: indexed count, 2: skipped count, 3: failed count, 4: post type slug */
            'message'  => sprintf(
                __('Reindexed "%4$s": %1$d indexed, %2$d skipped, %3$d failed.', 'typesense-search'),
                $indexed,
                $skipped,
                $failed,
                $postType
            ),
            'indexed'  => $indexed,
            'skipped'  => $skipped,
            'failed'   => $failed,
            'postType' => $postType,
        ]);
    }

    // ── 7. Get facetable fields ───────────────────────────────────────────────

    public function handleGetFacetFields(): void
    {
        check_ajax_referer(self::AJAX_ACTION_GET_FACET_FIELDS, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'typesense-search')], 403);
            return;
        }

        $remote         = (string) get_option(Settings::OPTION_REMOTE, '');
        $adminKey       = (string) get_option(Settings::OPTION_ADMIN_KEY, '');
        $collectionName = (string) get_option(Settings::OPTION_INDEX_NAME, '');

        if (empty($remote) || empty($adminKey) || empty($collectionName)) {
            wp_send_json_error(['message' => __('Connection settings are incomplete. Please configure the connection first.', 'typesense-search')]);
            return;
        }

        try {
            $client     = ClientFactory::build($remote, $adminKey);
            $collection = $client->collections[$collectionName]->retrieve();

            $facetableFields = [];
            foreach ($collection['fields'] ?? [] as $field) {
                if (!empty($field['facet']) && ($field['name'] ?? '') !== '.*') {
                    $facetableFields[] = [
                        'name' => $field['name'],
                        'type' => $field['type'] ?? 'string',
                    ];
                }
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: error message */
                    __('Could not fetch collection schema: %s', 'typesense-search'),
                    $e->getMessage()
                ),
            ]);
            return;
        }

        wp_send_json_success(['fields' => $facetableFields]);
    }

    // ── 7. Check saved-settings status ────────────────────────────────────────

    /**
     * Server-side health check using the credentials stored in WordPress options.
     *
     * Returns a structured object with three checks:
     *  - connection : can the server be reached and is it healthy?
     *  - adminKey   : does the admin key allow listing collections?
     *  - searchKey  : can the search key search the configured collection?
     *
     * When the search key check fails the response also includes `searchKeyCanFix: true`
     * so the front-end can offer a "Create new search key" button.
     */
    public function handleCheckStatus(): void
    {
        check_ajax_referer(self::AJAX_ACTION_CHECK_STATUS, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'typesense-search')], 403);
            return;
        }

        $remote         = (string) get_option(Settings::OPTION_REMOTE, '');
        $adminKey       = (string) get_option(Settings::OPTION_ADMIN_KEY, '');
        $searchKey      = (string) get_option(Settings::OPTION_SEARCH_KEY, '');
        $collectionName = (string) get_option(Settings::OPTION_INDEX_NAME, '');

        $result = [
            'connection'        => ['ok' => false, 'message' => ''],
            'adminKey'          => ['ok' => false, 'message' => ''],
            'collection'        => ['ok' => false, 'message' => ''],
            'searchKey'         => ['ok' => false, 'message' => ''],
            'collectionCanFix'  => false,
            'searchKeyCanFix'   => false,
        ];

        // ── 1. Connection ────────────────────────────────────────────────────

        if (empty($remote)) {
            $result['connection']['message'] = __('No host URL configured.', 'typesense-search');
            wp_send_json_success($result);
            return;
        }

        try {
            $client = ClientFactory::build($remote, $adminKey ?: 'placeholder', 5);
            $health = $client->health->retrieve();

            if (!empty($health['ok'])) {
                $result['connection']['ok']      = true;
                $result['connection']['message'] = __('Server is reachable and healthy.', 'typesense-search');
            } else {
                $result['connection']['message'] = __('Server responded but reported an unhealthy status.', 'typesense-search');
                wp_send_json_success($result);
                return;
            }
        } catch (\Exception $e) {
            $result['connection']['message'] = sprintf(
                /* translators: %s: error message */
                __('Could not reach server: %s', 'typesense-search'),
                $e->getMessage()
            );
            wp_send_json_success($result);
            return;
        }

        // ── 2. Admin key ─────────────────────────────────────────────────────

        if (empty($adminKey)) {
            $result['adminKey']['message'] = __('No admin key configured.', 'typesense-search');
        } else {
            try {
                $client = ClientFactory::build($remote, $adminKey, 5);
                $client->collections->retrieve();
                $result['adminKey']['ok']      = true;
                $result['adminKey']['message'] = __('Admin key is valid.', 'typesense-search');
            } catch (RequestUnauthorized $e) {
                $result['adminKey']['message'] = __('Admin key was rejected by the server.', 'typesense-search');
            } catch (\Exception $e) {
                $result['adminKey']['message'] = sprintf(
                    /* translators: %s: error message */
                    __('Admin key check failed: %s', 'typesense-search'),
                    $e->getMessage()
                );
            }
        }

        // ── 3. Collection existence ──────────────────────────────────────────

        if (!$result['adminKey']['ok']) {
            $result['collection']['message'] = __('Cannot check — admin key is not valid.', 'typesense-search');
        } elseif (empty($collectionName)) {
            $result['collection']['message'] = __('No collection name configured.', 'typesense-search');
        } else {
            try {
                $adminClient = ClientFactory::build($remote, $adminKey, 5);
                if (Collection::exists($adminClient, $collectionName)) {
                    $result['collection']['ok']      = true;
                    $result['collection']['message'] = sprintf(
                        /* translators: %s: collection name */
                        __('Collection "%s" exists.', 'typesense-search'),
                        $collectionName
                    );
                } else {
                    $result['collection']['message'] = sprintf(
                        /* translators: %s: collection name */
                        __('Collection "%s" does not exist.', 'typesense-search'),
                        $collectionName
                    );
                    $result['collectionCanFix'] = true;
                }
            } catch (\Exception $e) {
                $result['collection']['message'] = sprintf(
                    /* translators: %s: error message */
                    __('Collection check failed: %s', 'typesense-search'),
                    $e->getMessage()
                );
            }
        }

        // ── 4. Search key ─────────────────────────────────────────────────────

        if (empty($searchKey)) {
            $result['searchKey']['message'] = __('No search key configured.', 'typesense-search');
            $result['searchKeyCanFix']      = $result['adminKey']['ok'] && !empty($collectionName);
        } elseif (empty($collectionName)) {
            $result['searchKey']['message'] = __('No collection name configured — cannot test the search key.', 'typesense-search');
        } else {
            try {
                $searchClient = ClientFactory::build($remote, $searchKey, 5);
                $searchClient->collections[$collectionName]->documents->search([
                    'q'        => '*',
                    'query_by' => 'title',
                    'per_page' => 0,
                ]);
                $result['searchKey']['ok']      = true;
                $result['searchKey']['message'] = sprintf(
                    /* translators: %s: collection name */
                    __('Search key works against collection "%s".', 'typesense-search'),
                    $collectionName
                );
            } catch (RequestUnauthorized $e) {
                $result['searchKey']['message'] = sprintf(
                    /* translators: %s: collection name */
                    __('Search key does not have access to collection "%s".', 'typesense-search'),
                    $collectionName
                );
                // We can always create a new correctly-scoped key if the admin key works
                $result['searchKeyCanFix'] = $result['adminKey']['ok'];
            } catch (\Exception $e) {
                $result['searchKey']['message'] = sprintf(
                    /* translators: %s: error message */
                    __('Search key check failed: %s', 'typesense-search'),
                    $e->getMessage()
                );
            }
        }

        wp_send_json_success($result);
    }

    // ── 8. Fix (regenerate) search key ────────────────────────────────────────

    /**
     * Generate a new search key scoped to the configured collection and persist
     * it directly to the WordPress options table.
     *
     * This is the one-click "fix" offered when the current search key is not
     * working against the configured collection. Because Typesense does not
     * support modifying an existing key's collection scope, the only remedy is
     * to create a new, correctly-scoped key.
     */
    public function handleFixSearchKey(): void
    {
        check_ajax_referer(self::AJAX_ACTION_FIX_SEARCH_KEY, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'typesense-search')], 403);
            return;
        }

        $remote         = (string) get_option(Settings::OPTION_REMOTE, '');
        $adminKey       = (string) get_option(Settings::OPTION_ADMIN_KEY, '');
        $collectionName = (string) get_option(Settings::OPTION_INDEX_NAME, '');

        if (empty($remote) || empty($adminKey) || empty($collectionName)) {
            wp_send_json_error([
                'message' => __('Host, admin key, and collection name must all be saved before a new search key can be created.', 'typesense-search'),
            ]);
            return;
        }

        try {
            $client = ClientFactory::build($remote, $adminKey);
            $newKey = ApiKey::generateSearchKey($client, $collectionName);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: error message */
                    __('Could not create search key: %s', 'typesense-search'),
                    $e->getMessage()
                ),
            ]);
            return;
        }

        update_option(Settings::OPTION_SEARCH_KEY, $newKey);

        wp_send_json_success([
            'message' => __('New search key created and saved. The status checks should now pass.', 'typesense-search'),
        ]);
    }

    // ── 9. Create collection from saved settings (Status tab) ─────────────────

    /**
     * Create the Typesense collection using the credentials stored in options.
     * Used by the one-click "Create collection" button on the Status tab.
     */
    public function handleStatusCreateCollection(): void
    {
        check_ajax_referer(self::AJAX_ACTION_STATUS_CREATE_COL, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'typesense-search')], 403);
            return;
        }

        $remote         = (string) get_option(Settings::OPTION_REMOTE, '');
        $adminKey       = (string) get_option(Settings::OPTION_ADMIN_KEY, '');
        $collectionName = (string) get_option(Settings::OPTION_INDEX_NAME, '');

        if (empty($remote) || empty($adminKey) || empty($collectionName)) {
            wp_send_json_error([
                'message' => __('Host, admin key, and collection name must all be saved before the collection can be created.', 'typesense-search'),
            ]);
            return;
        }

        try {
            $client = ClientFactory::build($remote, $adminKey);
            Collection::create($client, $collectionName);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: error message */
                    __('Could not create collection: %s', 'typesense-search'),
                    $e->getMessage()
                ),
            ]);
            return;
        }

        wp_send_json_success([
            'message' => sprintf(
                /* translators: %s: collection name */
                __('Collection "%s" created successfully.', 'typesense-search'),
                $collectionName
            ),
        ]);
    }
}
