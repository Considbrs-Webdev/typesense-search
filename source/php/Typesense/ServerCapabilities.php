<?php

namespace TypesenseSearch\Typesense;

/**
 * Detects Typesense server capabilities that depend on the server version.
 */
class ServerCapabilities
{
    private const MIN_CURATION_SETS_VERSION = '30.0.0';

    private ?string $cached = null;

    public function __construct(private AdminApi $adminApi)
    {
    }

    public function supportsCurationSets(): bool
    {
        $version = $this->getServerVersion();

        return $version !== '' && version_compare($version, self::MIN_CURATION_SETS_VERSION, '>=');
    }

    /**
     * Returns the server version string, cached per-instance, or '' on failure.
     */
    public function getServerVersion(): string
    {
        return $this->cached ??= $this->adminApi->getServerVersion();
    }
}
