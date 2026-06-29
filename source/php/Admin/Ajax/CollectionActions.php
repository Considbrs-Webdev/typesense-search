<?php

namespace TypesenseSearch\Admin\Ajax;

use TypesenseSearch\Admin\SettingsAjax;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Typesense\ClientFactory;
use TypesenseSearch\Typesense\Collection;

/**
 * AJAX handlers for creating Typesense collections.
 *
 * Covers:
 *   - typesense_create_collection      (handleCreateCollection) – creates from live form fields
 *   - typesense_status_create_collection (handleStatusCreateCollection) – creates from saved options
 *
 * @package TypesenseSearch\Admin\Ajax
 */
class CollectionActions
{
    use AjaxHelpers;

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function register(): void
    {
        add_action('wp_ajax_' . SettingsAjax::AJAX_ACTION_CREATE_COL,        [$this, 'handleCreateCollection']);
        add_action('wp_ajax_' . SettingsAjax::AJAX_ACTION_STATUS_CREATE_COL, [$this, 'handleStatusCreateCollection']);
    }

    // ── 1. Create collection (from live form fields) ─────────────────────────

    public function handleCreateCollection(): void
    {
        ['remote' => $remote, 'adminKey' => $adminKey] = $this->requireConnectionFields(SettingsAjax::AJAX_ACTION_CREATE_COL);

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

    // ── 2. Create collection from saved settings (Status tab) ─────────────────

    /**
     * Create the Typesense collection using the credentials stored in options.
     * Used by the one-click "Create collection" button on the Status tab.
     */
    public function handleStatusCreateCollection(): void
    {
        $this->requirePermission(SettingsAjax::AJAX_ACTION_STATUS_CREATE_COL);

        $remote         = $this->settings->getRemote();
        $adminKey       = $this->settings->getAdminKey();
        $collectionName = $this->settings->getCollectionName();

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
