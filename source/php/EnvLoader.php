<?php

namespace TypesenseSearch;

use TypesenseSearch\Admin\Settings;

/**
 * Loads connection settings from a .env file placed in the plugin directory.
 *
 * When a variable is present in .env it is returned instead of the database
 * option, and any attempt to save a new value for that option via the WordPress
 * settings form is silently blocked. This lets teams store sensitive credentials
 * outside of the WordPress database without any code changes elsewhere in the
 * plugin.
 *
 * Supported variables and the WP option they override:
 *
 *   TYPESENSE_HOST            → typesense_search_remote
 *   TYPESENSE_FRONTEND_HOST   → typesense_search_frontend_host
 *   TYPESENSE_COLLECTION      → typesense_search_index_name
 *   TYPESENSE_ADMIN_KEY       → typesense_search_admin_key
 *   TYPESENSE_SEARCH_KEY      → typesense_search_search_key
 *
 * The .env format is one KEY=VALUE pair per line. Lines starting with # are
 * comments. Surrounding whitespace and single/double quotes around values are
 * stripped. Example:
 *
 *   TYPESENSE_HOST=https://search.example.com
 *   TYPESENSE_ADMIN_KEY="my-secret-key"
 *
 * @package TypesenseSearch
 */
class EnvLoader
{
    /**
     * WP option_name => value for every setting overridden by .env.
     *
     * Stored statically so the view can query it without holding a reference
     * to the instance.
     *
     * @var array<string, string>
     */
    private static array $overrides = [];

    /**
     * Map of .env variable names to WordPress option names.
     *
     * @var array<string, string>
     */
    private const ENV_MAP = [
        'TYPESENSE_HOST'          => Settings::OPTION_REMOTE,
        'TYPESENSE_FRONTEND_HOST' => Settings::OPTION_FRONTEND_HOST,
        'TYPESENSE_COLLECTION'    => Settings::OPTION_INDEX_NAME,
        'TYPESENSE_ADMIN_KEY'     => Settings::OPTION_ADMIN_KEY,
        'TYPESENSE_SEARCH_KEY'    => Settings::OPTION_SEARCH_KEY,
    ];

    public function __construct()
    {
        $this->parseEnvFile();
        $this->registerOptionFilters();
    }

    /**
     * Parse the .env file located at the plugin root and populate
     * self::$overrides with the values that correspond to known option names.
     */
    private function parseEnvFile(): void
    {
        $envFile = TYPESENSESEARCH_PATH . '.env';

        if (!file_exists($envFile) || !is_readable($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = ltrim($line);

            // Skip blank lines and comments
            if ($trimmed === '' || $trimmed[0] === '#') {
                continue;
            }

            if (!str_contains($trimmed, '=')) {
                continue;
            }

            [$rawKey, $rawValue] = explode('=', $trimmed, 2);

            $key   = trim($rawKey);
            // Strip surrounding whitespace and optional single/double quotes
            $value = trim($rawValue, " \t\n\r\0\x0B\"'");

            if (!array_key_exists($key, self::ENV_MAP) || $value === '') {
                continue;
            }

            self::$overrides[self::ENV_MAP[$key]] = $value;
        }
    }

    /**
     * Hook into WordPress so that:
     *
     * 1. `get_option()` for an env-defined option always returns the .env value.
     * 2. `update_option()` for an env-defined option is a no-op (returns the
     *    existing db value so WordPress considers it unchanged and skips the
     *    UPDATE query).
     */
    private function registerOptionFilters(): void
    {
        foreach (self::$overrides as $optionName => $value) {
            add_filter(
                'pre_option_' . $optionName,
                static function () use ($value): string {
                    return $value;
                },
                10,
                0
            );

            add_filter(
                'pre_update_option_' . $optionName,
                static function (mixed $newValue, mixed $oldValue): mixed {
                    // Return the existing value so WordPress skips the update.
                    return $oldValue;
                },
                10,
                2
            );
        }
    }

    /**
     * Return true if the given WordPress option name is currently overridden
     * by a value in the .env file.
     *
     * Use this in admin views to decide whether to render a field as read-only.
     *
     * @param string $optionName  e.g. Settings::OPTION_REMOTE
     */
    public static function isDefinedInEnv(string $optionName): bool
    {
        return isset(self::$overrides[$optionName]);
    }
}
