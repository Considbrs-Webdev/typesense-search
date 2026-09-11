<?php

namespace TypesenseSearch\Admin\Ajax;

/**
 * Shared AJAX guard helpers used by every action class in this namespace.
 *
 * Provides two helpers that action classes include via `use AjaxHelpers;`:
 *   - requirePermission()      – nonce + capability gate for standard handlers
 *   - requireConnectionFields() – nonce + capability gate + POST validation for
 *                                 handlers that accept live connection credentials
 *
 * @package TypesenseSearch\Admin\Ajax
 */
trait AjaxHelpers
{
    private function requireNetworkPolicy(string $action): void
    {
        $network = new \TypesenseSearch\Multisite\NetworkSettingsRepository();
        if (!$network->isNetworkActivated()) {
            return;
        }
        $networkActions = ['typesense_test_connection', 'typesense_create_collection',
            'typesense_generate_search_key', 'typesense_check_status',
            'typesense_fix_search_key', 'typesense_status_create_collection'];
        if (in_array($action, $networkActions, true)) {
            wp_send_json_error(['message' => __('Use Network Admin to manage the connection and keys.', 'typesense-search')], 403);
        }
        if ($action !== 'typesense_clear_indexing_log' && !$network->canUse()) {
            wp_send_json_error(['message' => __('Typesense is disabled or not ready for this site.', 'typesense-search')], 403);
        }
    }

    /**
     * Verify the nonce and confirm the current user has manage_options capability.
     * Sends a JSON 403 error and terminates if either check fails.
     */
    private function requirePermission(string $nonce): void
    {
        check_ajax_referer($nonce, 'nonce');
        $this->requireNetworkPolicy($nonce);

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'typesense-search')], 403);
        }
    }

    /**
     * Validate shared POST fields and return them, or send a JSON error and terminate.
     *
     * @return array{remote: string, adminKey: string}
     */
    private function requireConnectionFields(string $nonce): array
    {
        check_ajax_referer($nonce, 'nonce');
        $this->requireNetworkPolicy($nonce);

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
}
