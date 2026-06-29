<?php

namespace TypesenseSearch\Typesense;

use TypesenseSearch\Services\SettingsRepository;

/**
 * Low-level HTTP wrapper for the Typesense admin REST API.
 *
 * Centralises raw wp_remote_* calls that previously lived in TypesenseSync
 * and ServerCapabilities so they can be injected and tested.
 */
class AdminApi
{
    public function __construct(private SettingsRepository $settings)
    {
    }

    /**
     * Fetch the Typesense server version string, or '' on any failure.
     */
    public function getServerVersion(): string
    {
        $remote = (string) $this->settings->getRemote();
        if ($remote === '') {
            return '';
        }

        $adminKey = $this->settings->getAdminKey();
        $endpoint = trailingslashit($remote) . 'debug';
        $headers  = [];
        if ($adminKey !== '') {
            $headers['X-TYPESENSE-API-KEY'] = $adminKey;
        }

        $response = wp_remote_get($endpoint, [
            'headers' => $headers,
            'timeout' => 5,
        ]);

        if (is_wp_error($response)) {
            return '';
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        if ($statusCode < 200 || $statusCode >= 300) {
            return '';
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['version'])) {
            return '';
        }

        $version = trim((string) $body['version']);
        $version = ltrim($version, 'vV');

        return $version;
    }

    /**
     * Make an authenticated request to the Typesense REST API.
     *
     * The admin key is read from SettingsRepository and sent as
     * X-TYPESENSE-API-KEY. Returns a normalised result array:
     *   ['ok' => bool, 'message' => string, 'body' => string]
     *
     * @param array<string, mixed>|null $body JSON-encodable body, or null for bodyless requests.
     * @return array{ok: bool, message: string, body: string}
     */
    public function request(string $method, string $url, ?array $body = null): array
    {
        $args = [
            'method'  => $method,
            'timeout' => 10,
            'headers' => [
                'Content-Type'        => 'application/json',
                'X-TYPESENSE-API-KEY' => $this->settings->getAdminKey(),
            ],
        ];

        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return ['ok' => false, 'message' => $response->get_error_message(), 'body' => ''];
        }

        $statusCode   = (int) wp_remote_retrieve_response_code($response);
        $responseBody = (string) wp_remote_retrieve_body($response);

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = trim($responseBody);

            return [
                'ok'      => false,
                'message' => $message !== ''
                    ? sprintf('Typesense returned HTTP %d: %s', $statusCode, $message)
                    : sprintf('Typesense returned HTTP %d.', $statusCode),
                'body'    => $responseBody,
            ];
        }

        return ['ok' => true, 'message' => '', 'body' => $responseBody];
    }
}
