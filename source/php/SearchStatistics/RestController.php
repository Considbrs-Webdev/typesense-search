<?php

namespace TypesenseSearch\SearchStatistics;

use TypesenseSearch\Services\SettingsRepository;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Receives same-origin, browser-side search telemetry.
 */
class RestController
{
    public const REST_NAMESPACE = 'typesense-search/v1';
    public const REST_ROUTE = '/search-statistics';

    public function __construct(private SettingsRepository $settings, private Repository $repository)
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
            'methods'             => 'POST',
            'callback'            => [$this, 'record'],
            // The endpoint carries no authenticated action and only accepts a
            // small, validated telemetry payload. Browsers cannot POST JSON
            // cross-origin without this site's explicit CORS permission.
            'permission_callback' => '__return_true',
        ]);
    }

    public function record(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->settings->isSearchLoggingEnabled()) {
            return new WP_REST_Response(['recorded' => false], 204);
        }

        $query = (string) $request->get_param('query');
        $sessionId = (string) $request->get_param('session_id');
        $surface = (string) $request->get_param('surface');
        $found = absint($request->get_param('found'));

        if (!$this->repository->canRecord($query, $this->settings) || !preg_match('/^[a-zA-Z0-9_-]{20,128}$/', $sessionId)) {
            return new WP_REST_Response(['recorded' => false], 400);
        }

        $recorded = $this->repository->record($query, $found, $surface, $sessionId);

        return new WP_REST_Response(['recorded' => $recorded], $recorded ? 201 : 200);
    }
}
