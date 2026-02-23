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
    public const AJAX_ACTION_TEST       = 'typesense_test_connection';
    public const AJAX_ACTION_CREATE_COL = 'typesense_create_collection';
    public const AJAX_ACTION_GEN_KEY    = 'typesense_generate_search_key';

    public function __construct()
    {
        add_action('wp_ajax_' . self::AJAX_ACTION_TEST,       [$this, 'handle']);
        add_action('wp_ajax_' . self::AJAX_ACTION_CREATE_COL, [$this, 'handleCreateCollection']);
        add_action('wp_ajax_' . self::AJAX_ACTION_GEN_KEY,    [$this, 'handleGenerateSearchKey']);
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
}
