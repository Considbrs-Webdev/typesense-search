<?php

namespace TypesenseSearch\Helper;

/**
 * Class CacheBust
 * 
 * Handles resolving hashed filenames from the Vite manifest.
 * 
 * @package TypesenseSearch\Helper
 */
class CacheBust
{
    private static ?array $manifest = null;

    /**
     * Get the hashed filename from the manifest
     *
     * @param string $name The original filename (e.g., 'css/modularity-service-info.css')
     * @return string|false The hashed filename or false if not found
     */
    public static function name(string $name): string|false
    {
        $manifest = self::getManifest();

        if ($manifest && isset($manifest[$name])) {
            return $manifest[$name];
        }

        return false;
    }

    /**
     * Load and cache the manifest file
     *
     * @return array|null The manifest array or null if not found
     */
    private static function getManifest(): ?array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        $manifestPath = TYPESENSESEARCH_PATH . 'assets/dist/manifest.json';

        if (file_exists($manifestPath)) {
            $manifestContent = file_get_contents($manifestPath);
            self::$manifest = json_decode($manifestContent, true);
            return self::$manifest;
        }

        return null;
    }
}

