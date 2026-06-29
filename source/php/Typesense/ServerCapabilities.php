<?php

namespace TypesenseSearch\Typesense;

use TypesenseSearch\Services\SettingsRepository;

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

    /**
     * Returns the server version string, cached per-request, or '' on failure.
     *
     * HTTP logic lives in AdminApi; this wrapper preserves the static interface
     * that Collection::getSchema() and other callers depend on.
     */
    public static function getServerVersion(): string
    {
        static $cached = null;

        if (is_string($cached)) {
            return $cached;
        }

        $adminApi = new AdminApi(new SettingsRepository());
        return $cached = $adminApi->getServerVersion();
    }
}
