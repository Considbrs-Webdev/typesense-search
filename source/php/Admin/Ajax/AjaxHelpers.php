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
    /**
     * Verify the nonce and confirm the current user has manage_options capability.
     * Sends a JSON 403 error and terminates if either check fails.
     */
    private function requirePermission(string $nonce): void
    {
        check_ajax_referer($nonce, 'nonce');

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
