<?php

namespace TypesenseSearch\PinnedResults;

use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Typesense\ServerCapabilities;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Admin-only REST API for pinned search result rules.
 */
class RestController
{
    public const REST_NAMESPACE = 'typesense-search/v1';
    public const REST_ROUTE = '/pinned-results';

    public function __construct(
        private SettingsRepository $settings,
        private Repository $repository,
        private TypesenseSync $sync,
        private ServerCapabilities $capabilities
    ) {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'list'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'save'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE . '/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'delete'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'id' => [
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE . '/posts', [
            'methods'             => 'GET',
            'callback'            => [$this, 'searchPosts'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE . '/sync', [
            'methods'             => 'POST',
            'callback'            => [$this, 'sync'],
            'permission_callback' => [$this, 'canManage'],
        ]);
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    public function list(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $guard = $this->guardFeature();
        if (is_wp_error($guard)) {
            return $guard;
        }

        return $this->response([
            'rules' => $this->repository->all(),
        ]);
    }

    public function save(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $guard = $this->guardFeature();
        if (is_wp_error($guard)) {
            return $guard;
        }

        $body = json_decode((string) $request->get_body(), true);
        if (!is_array($body)) {
            $body = $request->get_params();
        }

        $id = isset($body['id']) ? absint($body['id']) : null;
        $phrase = sanitize_text_field((string) ($body['phrase'] ?? ''));
        $matchType = sanitize_key((string) ($body['match_type'] ?? 'exact'));
        $postIds = is_array($body['post_ids'] ?? null) ? (array) $body['post_ids'] : [];
        $enabled = !array_key_exists('enabled', $body) || (bool) $body['enabled'];

        $saved = $this->repository->save($id ?: null, $phrase, $matchType, $postIds, $enabled);
        if (is_wp_error($saved)) {
            $this->sendNoCacheHeaders();
            return $saved;
        }

        return $this->response([
            'rule' => $saved,
        ], 200);
    }

    public function delete(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $guard = $this->guardFeature();
        if (is_wp_error($guard)) {
            return $guard;
        }

        $this->repository->delete(absint($request['id']));

        return $this->response([
            'deleted' => true,
            'rules'   => $this->repository->all(),
        ]);
    }

    public function searchPosts(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $guard = $this->guardFeature();
        if (is_wp_error($guard)) {
            return $guard;
        }

        return $this->response([
            'posts' => $this->repository->searchPosts((string) $request->get_param('search')),
        ]);
    }

    public function sync(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $guard = $this->guardFeature();
        if (is_wp_error($guard)) {
            return $guard;
        }

        $result = $this->sync->sync($this->repository->all());

        if ($result['ok']) {
            $this->repository->markSynced();
        } else {
            $this->repository->markSyncError($result['message']);
        }

        return $this->response([
            'ok'      => $result['ok'],
            'message' => $result['message'],
            'rules'   => $this->repository->all(),
        ], $result['ok'] ? 200 : 502);
    }

    private function guardFeature(): bool|\WP_Error
    {
        if (!$this->settings->isPinnedResultsEnabled() || !$this->capabilities->supportsCurationSets()) {
            $this->sendNoCacheHeaders();

            return new \WP_Error(
                'pinned_results_unavailable',
                __('Pinned results are not enabled or this Typesense server does not support curation sets.', 'typesense-search'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function response(array $data, int $status = 200): WP_REST_Response
    {
        $this->sendNoCacheHeaders();

        $response = new WP_REST_Response($data, $status);
        foreach ($this->noCacheHeaders() as $header => $value) {
            $response->header($header, $value);
        }

        return $response;
    }

    private function sendNoCacheHeaders(): void
    {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        if (function_exists('nocache_headers')) {
            nocache_headers();
        }

        foreach ($this->noCacheHeaders() as $header => $value) {
            header($header . ': ' . $value);
        }

        do_action('litespeed_control_set_nocache', 'Typesense pinned results REST');
    }

    /**
     * @return array<string, string>
     */
    private function noCacheHeaders(): array
    {
        return [
            'Cache-Control'             => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'                    => 'no-cache',
            'Expires'                   => '0',
            'X-LiteSpeed-Cache-Control' => 'no-cache',
        ];
    }
}
