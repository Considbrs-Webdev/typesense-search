<?php

namespace TypesenseSearch\Typesense;

use TypesenseSearch\Admin\Settings;

/**
 * Detects Typesense server capabilities that depend on the server version.
 */
class ServerCapabilities
{
    private const MIN_CURATION_SETS_VERSION = '30.0.0';

    public static function supportsCurationSets(): bool
    {
        $version = self::getServerVersion();

        return $version !== '' && version_compare($version, self::MIN_CURATION_SETS_VERSION, '>=');
    }

    public static function getServerVersion(): string
    {
        static $cached = null;

        if (is_string($cached)) {
            return $cached;
        }

        $remote = (string) get_option(Settings::OPTION_REMOTE, '');
        if ($remote === '') {
            return $cached = '';
        }

        $endpoint = trailingslashit($remote) . 'debug';
        $headers = [];
        $adminKey = (string) get_option(Settings::OPTION_ADMIN_KEY, '');
        if ($adminKey !== '') {
            $headers['X-TYPESENSE-API-KEY'] = $adminKey;
        }

        $response = wp_remote_get($endpoint, [
            'headers' => $headers,
            'timeout' => 5,
        ]);

        if (is_wp_error($response)) {
            return $cached = '';
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        if ($statusCode < 200 || $statusCode >= 300) {
            return $cached = '';
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['version'])) {
            return $cached = '';
        }

        $version = self::normalizeVersion((string) $body['version']);

        return $cached = $version;
    }

    private static function normalizeVersion(string $version): string
    {
        $version = trim($version);
        $version = ltrim($version, 'vV');

        return $version;
    }
}
