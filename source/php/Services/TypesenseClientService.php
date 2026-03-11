<?php

namespace TypesenseSearch\Services;

use Typesense\Client;
use TypesenseSearch\Typesense\ClientFactory;

/**
 * Class TypesenseClientService
 *
 * Injectable service that provides a single, lazily-created Typesense client
 * for the duration of the current request.
 *
 * Unlike calling ClientFactory::fromOptions() directly, this service:
 *   - Caches the client instance so credentials are only read and parsed once
 *     per request, regardless of how many strategies or hooks call getClient().
 *   - Can be injected as a dependency, making consumers (strategies, CLI
 *     commands, etc.) testable via a mock or stub without a live server.
 *
 * Typical usage:
 *
 *   $client = $this->clientService->getClient();
 *   if ($client === null) {
 *       return; // not configured, bail out silently
 *   }
 *
 * @package TypesenseSearch\Services
 */
class TypesenseClientService
{
    private SettingsRepository $settings;

    /**
     * Cached client instance. Null until the first successful build.
     */
    private ?Client $client = null;

    /**
     * Set to true once a build attempt has been made so that unconfigured
     * installations do not repeat the option reads on every getClient() call.
     */
    private bool $attempted = false;

    public function __construct(SettingsRepository $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Return the Typesense client, building it lazily on first call.
     *
     * Returns null when the remote URL or admin key are not yet configured.
     */
    public function getClient(): ?Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        if ($this->attempted) {
            return null;
        }

        $this->attempted = true;

        $remote   = $this->settings->getRemote();
        $adminKey = $this->settings->getAdminKey();

        if (empty($remote) || empty($adminKey)) {
            return null;
        }

        $this->client = ClientFactory::build($remote, $adminKey);

        return $this->client;
    }

    /**
     * Returns true when the client can be built and the server responds healthy.
     */
    public function isReady(): bool
    {
        $client = $this->getClient();

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
}
