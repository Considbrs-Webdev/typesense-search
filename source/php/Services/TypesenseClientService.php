<?php

namespace TypesenseSearch\Services;

use Typesense\Client;
use TypesenseSearch\Typesense\ClientFactory;

/**
 * Class TypesenseClientService
 *
 * Injectable service that provides a single, lazily-created Typesense client
 * for the current effective site configuration.
 *
 * Unlike calling ClientFactory::fromOptions() directly, this service:
 *   - Reuses the client while the effective connection and collection match,
 *     and invalidates it when the current site/configuration changes.
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
     * Avoid repeated build attempts while the effective configuration is unchanged.
     */
    private bool $attempted = false;
    private string $context = '';

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
        $remote = $this->settings->getRemote();
        $adminKey = $this->settings->getAdminKey();
        $context = hash('sha256', $remote . '|' . $adminKey . '|' . $this->settings->getCollectionName());
        if ($this->context !== $context) {
            $this->client = null;
            $this->attempted = false;
            $this->context = $context;
        }
        if ($this->client !== null) {
            return $this->client;
        }

        if ($this->attempted) {
            return null;
        }

        $this->attempted = true;

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
