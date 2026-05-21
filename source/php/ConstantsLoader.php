<?php

namespace TypesenseSearch;

use TypesenseSearch\Admin\Settings;

/**
 * Reads Typesense connection settings from PHP constants.
 *
 * When a constant is defined it takes priority over the database option, and
 * any attempt to save a new value for that option via the WordPress settings
 * form is silently blocked. Define constants in a config file (e.g. inside
 * wp-content/config/) before WordPress loads this plugin.
 *
 * Supported constants and the WP option they override:
 *
 *   TYPESENSE_HOST            → typesense_search_remote
 *   TYPESENSE_FRONTEND_HOST   → typesense_search_frontend_host
 *   TYPESENSE_COLLECTION      → typesense_search_index_name
 *   TYPESENSE_ADMIN_KEY       → typesense_search_admin_key
 *   TYPESENSE_SEARCH_KEY      → typesense_search_search_key
 *
 * Example (in a config file loaded by wp-config.php):
 *
 *   define('TYPESENSE_HOST', 'https://search.example.com');
 *   define('TYPESENSE_ADMIN_KEY', 'my-secret-key');
 *
 * @package TypesenseSearch
 */
class ConstantsLoader
{
    /**
     * WP option_name => value for every setting overridden by a constant.
     *
     * Stored statically so the view can query it without holding a reference
     * to the instance.
     *
     * @var array<string, string>
     */
    private static array $overrides = [];

    /**
     * Map of PHP constant names to WordPress option names.
     *
     * @var array<string, string>
     */
    private const CONSTANT_MAP = [
        'TYPESENSE_HOST'          => Settings::OPTION_REMOTE,
        'TYPESENSE_FRONTEND_HOST' => Settings::OPTION_FRONTEND_HOST,
        'TYPESENSE_COLLECTION'    => Settings::OPTION_INDEX_NAME,
        'TYPESENSE_ADMIN_KEY'     => Settings::OPTION_ADMIN_KEY,
        'TYPESENSE_SEARCH_KEY'    => Settings::OPTION_SEARCH_KEY,
    ];

    public function __construct()
    {
        $this->loadConstants();
        $this->registerOptionFilters();
    }

    /**
     * Check each known constant and, when defined, store its value as an
     * override for the corresponding WordPress option.
     */
    private function loadConstants(): void
    {
        foreach (self::CONSTANT_MAP as $constantName => $optionName) {
            if (!defined($constantName)) {
                continue;
            }

            $value = constant($constantName);

            if (is_string($value) && $value !== '') {
                self::$overrides[$optionName] = $value;
            }
        }
    }

    /**
     * Hook into WordPress so that:
     *
     * 1. `get_option()` for a constant-defined option always returns the
     *    constant value.
     * 2. `update_option()` for a constant-defined option is a no-op (returns
     *    the existing db value so WordPress considers it unchanged and skips
     *    the UPDATE query).
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
     * by a PHP constant.
     *
     * Use this in admin views to decide whether to render a field as read-only.
     *
     * @param string $optionName  e.g. Settings::OPTION_REMOTE
     */
    public static function isDefinedAsConstant(string $optionName): bool
    {
        return isset(self::$overrides[$optionName]);
    }
}
