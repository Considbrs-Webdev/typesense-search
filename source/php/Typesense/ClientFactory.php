<?php

namespace TypesenseSearch\Typesense;

use Typesense\Client;
use TypesenseSearch\Admin\Settings;

/**
 * Class ClientFactory
 *
 * Centralises all Typesense client creation so every part of the plugin
 * (admin AJAX handlers, indexing hooks, …) uses the same connection logic.
 *
 * Typical usage in an indexing hook:
 *
 *   $client = ClientFactory::fromOptions();
 *   if ($client === null) {
 *       return; // not configured, bail out silently
 *   }
 *
 * @package TypesenseSearch\Typesense
 */
class ClientFactory
{
    /**
     * Build a Typesense client from explicit credentials.
     *
     * Use this when credentials come from user input (e.g. the settings-page
     * AJAX handlers) rather than from saved options.
     *
     * @param string $remote   Full URL, e.g. https://search.example.com:8108
     * @param string $apiKey   Admin or search-only API key.
     * @param int    $timeout  Connection timeout in seconds (default 5).
     */
    public static function build(string $remote, string $apiKey, int $timeout = 5): Client
    {
        $parsed   = parse_url($remote);
        $protocol = $parsed['scheme'] ?? 'https';
        $host     = $parsed['host'];
        $port     = (string) ($parsed['port'] ?? ($protocol === 'https' ? 443 : 80));

        return new Client([
            'api_key'                    => $apiKey,
            'nodes'                      => [
                [
                    'host'     => $host,
                    'port'     => $port,
                    'protocol' => $protocol,
                ],
            ],
            'connection_timeout_seconds' => $timeout,
            'num_retries'                => 1,
            'retry_interval_seconds'     => 0,
        ]);
    }

    /**
     * Build a Typesense client using the credentials stored in WordPress options.
     *
     * Returns null when the remote URL or admin key have not been saved yet, so
     * callers can bail out cleanly without attempting a broken connection.
     */
    public static function fromOptions(): ?Client
    {
        $remote   = (string) get_option(Settings::OPTION_REMOTE, '');
        $adminKey = (string) get_option(Settings::OPTION_ADMIN_KEY, '');

        if (empty($remote) || empty($adminKey)) {
            return null;
        }

        return self::build($remote, $adminKey);
    }

    /**
     * Quick guard: returns true when a client can be built *and* the server
     * responds as healthy.
     *
     * Intended for indexing / deletion hooks that should silently skip when
     * Typesense is not reachable, e.g.:
     *
     *   if (!ClientFactory::isReady()) return;
     */
    public static function isReady(): bool
    {
        $client = self::fromOptions();

        if ($client === null) {
            return false;
        }

        try {
            $health = $client->health->retrieve();
            return !empty($health['ok']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Returns true when the server is reachable *and* the configured collection
     * exists. Use this guard for features (such as custom view templates) that
     * should only activate when Typesense is fully operational.
     *
     * The result is cached in a static variable for the lifetime of the current
     * PHP request so repeated calls (e.g. a filter that fires multiple times)
     * do not trigger additional network round-trips.
     *
     *   if (!ClientFactory::isReadyWithCollection()) return;
     */
    public static function isReadyWithCollection(): bool
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $client = self::fromOptions();

        if ($client === null) {
            return $cache = false;
        }

        $collectionName = (string) get_option(Settings::OPTION_INDEX_NAME, '');

        if (empty($collectionName)) {
            return $cache = false;
        }

        try {
            $health = $client->health->retrieve();
            if (empty($health['ok'])) {
                return $cache = false;
            }
            
            return $cache = Collection::exists($client, $collectionName);
        } catch (\Exception $e) {
            return $cache = false;
        }
    }
}
