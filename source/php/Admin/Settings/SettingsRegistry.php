<?php

namespace TypesenseSearch\Admin\Settings;

/**
 * Registers all plugin settings with WordPress.
 *
 * Uses the Sanitizers trait so that sanitize_callback references can point
 * to methods on this object via [$this, 'sanitize*'].
 *
 * @package TypesenseSearch\Admin\Settings
 */
class SettingsRegistry
{
    use Sanitizers;

    /**
     * Register all plugin settings with WordPress.
     */
    public function registerSettings(): void
    {
        foreach ([
            OptionKeys::OPTION_REMOTE,
            OptionKeys::OPTION_INDEX_NAME,
            OptionKeys::OPTION_ADMIN_KEY,
            OptionKeys::OPTION_SEARCH_KEY,
            OptionKeys::OPTION_FRONTEND_HOST,
        ] as $option) {
            register_setting(OptionKeys::OPTION_GROUP_CONNECTION, $option, [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ]);
        }

        register_setting(OptionKeys::OPTION_GROUP_CONTENT, OptionKeys::OPTION_POST_TYPES, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitizePostTypes'],
            'default'           => [],
        ]);

        register_setting(OptionKeys::OPTION_GROUP_CONTENT, OptionKeys::OPTION_INDEX_MODULARITY, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]);

        register_setting(OptionKeys::OPTION_GROUP_ADVANCED_SETTINGS, OptionKeys::OPTION_DEBOUNCE, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 1,
        ]);

        register_setting(OptionKeys::OPTION_GROUP_ADVANCED_SETTINGS, OptionKeys::OPTION_DEBOUNCE_DELAY, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 300,
        ]);

        register_setting(OptionKeys::OPTION_GROUP_CONTENT, OptionKeys::OPTION_HITS_PER_PAGE, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 10,
        ]);

        register_setting(OptionKeys::OPTION_GROUP_ADVANCED_SETTINGS, OptionKeys::OPTION_HIGHLIGHT_AFFIX_NUM_TOKENS, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 15,
        ]);

        register_setting(OptionKeys::OPTION_GROUP_ADVANCED_SETTINGS, OptionKeys::OPTION_TRUNCATOR, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '[...]',
        ]);

        register_setting(OptionKeys::OPTION_GROUP_CONTENT, OptionKeys::OPTION_SORT_DISPLAY, [
            'type'              => 'string',
            'sanitize_callback' => static function (mixed $v): string {
                return in_array($v, ['radio', 'dropdown'], true) ? (string) $v : 'radio';
            },
            'default'           => 'radio',
        ]);

        register_setting(OptionKeys::OPTION_GROUP_ADVANCED_SETTINGS, OptionKeys::OPTION_QUERY_BY_WEIGHTS, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitizeQueryByWeights'],
            'default'           => self::getDefaultQueryByWeights(),
        ]);

        register_setting(OptionKeys::OPTION_GROUP_ADVANCED_SETTINGS, OptionKeys::OPTION_PINNED_RESULTS_ENABLED, [
            'type'              => 'integer',
            'sanitize_callback' => [$this, 'sanitizePinnedResultsEnabled'],
            'default'           => 0,
        ]);

        foreach ([
            OptionKeys::OPTION_SEARCH_LOGGING_ENABLED,
            OptionKeys::OPTION_SEARCH_LOGGING_DASHBOARD_WIDGETS,
            OptionKeys::OPTION_SEARCH_LOGGING_REQUIRE_CONSENT,
        ] as $option) {
            register_setting(OptionKeys::OPTION_GROUP_ADVANCED_SETTINGS, $option, [
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => $option === OptionKeys::OPTION_SEARCH_LOGGING_DASHBOARD_WIDGETS ? 1 : 0,
            ]);
        }

        register_setting(OptionKeys::OPTION_GROUP_ADVANCED_SETTINGS, OptionKeys::OPTION_SEARCH_LOGGING_DELAY_SECONDS, [
            'type'              => 'integer',
            'sanitize_callback' => static fn (mixed $value): int => min(30, max(0, absint($value))),
            'default'           => 1,
        ]);

        register_setting(OptionKeys::OPTION_GROUP_ADVANCED_SETTINGS, OptionKeys::OPTION_SEARCH_LOGGING_MINIMUM_CHARACTERS, [
            'type'              => 'integer',
            'sanitize_callback' => static fn (mixed $value): int => min(50, max(1, absint($value))),
            'default'           => 3,
        ]);

        register_setting(OptionKeys::OPTION_GROUP_ADVANCED_SETTINGS, OptionKeys::OPTION_SEARCH_STATISTICS_RETENTION_DAYS, [
            'type'              => 'integer',
            'sanitize_callback' => static fn (mixed $value): int => min(3650, max(1, absint($value))),
            'default'           => 90,
        ]);

        register_setting(OptionKeys::OPTION_GROUP_CONTENT, OptionKeys::OPTION_INDEX_PDF, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]);

        register_setting(OptionKeys::OPTION_GROUP_ADVANCED_SETTINGS, OptionKeys::OPTION_FACETS, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitizeFacets'],
            'default'           => [],
        ]);

        register_setting(OptionKeys::OPTION_GROUP_QUICK_SEARCH, OptionKeys::OPTION_QUICK_SEARCH_ENABLED, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]);

        register_setting(OptionKeys::OPTION_GROUP_QUICK_SEARCH, OptionKeys::OPTION_QUICK_SEARCH_SELECTORS, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitizeQuickSearchSelectors'],
            'default'           => [],
        ]);

        register_setting(OptionKeys::OPTION_GROUP_QUICK_SEARCH, OptionKeys::OPTION_QUICK_SEARCH_HITS_PER_PAGE, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 5,
        ]);
    }
}
