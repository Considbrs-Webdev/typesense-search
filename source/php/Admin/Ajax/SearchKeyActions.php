<?php

namespace TypesenseSearch\Admin\Ajax;

use TypesenseSearch\Admin\Settings;
use TypesenseSearch\Admin\SettingsAjax;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Typesense\ApiKey;
use TypesenseSearch\Typesense\ClientFactory;

/**
 * AJAX handlers for generating and fixing Typesense search API keys.
 *
 * Covers:
 *   - typesense_generate_search_key (handleGenerateSearchKey) – generates from live form fields
 *   - typesense_fix_search_key      (handleFixSearchKey)      – regenerates from saved options and persists
 *
 * @package TypesenseSearch\Admin\Ajax
 */
class SearchKeyActions
{
    use AjaxHelpers;

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function register(): void
    {
        add_action('wp_ajax_' . SettingsAjax::AJAX_ACTION_GEN_KEY,       [$this, 'handleGenerateSearchKey']);
        add_action('wp_ajax_' . SettingsAjax::AJAX_ACTION_FIX_SEARCH_KEY, [$this, 'handleFixSearchKey']);
    }

    // ── 1. Generate scoped search key ────────────────────────────────────────

    public function handleGenerateSearchKey(): void
    {
        ['remote' => $remote, 'adminKey' => $adminKey] = $this->requireConnectionFields(SettingsAjax::AJAX_ACTION_GEN_KEY);

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

    // ── 2. Fix (regenerate) search key ────────────────────────────────────────

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
        $this->requirePermission(SettingsAjax::AJAX_ACTION_FIX_SEARCH_KEY);

        $remote         = $this->settings->getRemote();
        $adminKey       = $this->settings->getAdminKey();
        $collectionName = $this->settings->getCollectionName();

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
}
