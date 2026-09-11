<?php

namespace TypesenseSearch\Typesense;

/**
 * Detects Typesense server capabilities that depend on the server version.
 */
class ServerCapabilities
{
    private const MIN_CURATION_SETS_VERSION = '30.0.0';
    private const MIN_SYNONYM_SETS_VERSION = '30.0.0';
    private const MIN_STEMMING_VERSION = '27.0.0';

    private ?string $cached = null;
    private ?string $context = null;

    public function __construct(private AdminApi $adminApi)
    {
    }

    public function supportsCurationSets(): bool
    {
        $version = $this->getServerVersion();

        return $version !== '' && version_compare($version, self::MIN_CURATION_SETS_VERSION, '>=');
    }

    public function supportsSynonymSets(): bool
    {
        $version = $this->getServerVersion();

        return $version !== '' && version_compare($version, self::MIN_SYNONYM_SETS_VERSION, '>=');
    }

    public function supportsStemming(): bool
    {
        $version = $this->getServerVersion();

        return $version !== '' && version_compare($version, self::MIN_STEMMING_VERSION, '>=');
    }

    /**
     * Returns the server version string, cached per-instance, or '' on failure.
     */
    public function getServerVersion(): string
    {
        $context = $this->adminApi->contextKey();
        if ($context !== $this->context) {
            $this->cached = null;
            $this->context = $context;
        }
        return $this->cached ??= $this->adminApi->getServerVersion();
    }
}
