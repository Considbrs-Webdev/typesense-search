<?php

namespace TypesenseSearch\Admin\Ajax;

use TypesenseSearch\Admin\Settings;
use TypesenseSearch\Admin\SettingsAjax;
use TypesenseSearch\Typesense\ClientFactory;

/**
 * AJAX handler for fetching the facetable fields of the configured collection.
 *
 * Covers:
 *   - typesense_get_facet_fields (handleGetFacetFields)
 *
 * @package TypesenseSearch\Admin\Ajax
 */
class FacetActions
{
    use AjaxHelpers;

    public function register(): void
    {
        add_action('wp_ajax_' . SettingsAjax::AJAX_ACTION_GET_FACET_FIELDS, [$this, 'handleGetFacetFields']);
    }

    // ── 1. Get facetable fields ───────────────────────────────────────────────

    public function handleGetFacetFields(): void
    {
        $this->requirePermission(SettingsAjax::AJAX_ACTION_GET_FACET_FIELDS);

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
}
