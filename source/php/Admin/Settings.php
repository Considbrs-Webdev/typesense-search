<?php

namespace TypesenseSearch\Admin;

use TypesenseSearch\Helper\CacheBust;
use TypesenseSearch\Frontend\I18n;

/**
 * Class Settings
 *
 * Registers the Typesense Search settings page under WordPress Settings.
 *
 * @package TypesenseSearch\Admin
 */
class Settings
{
    public const PAGE_SLUG = 'typesense-search';

    public const OPTION_GROUP_CONNECTION = 'typesense_search_connection';
    public const OPTION_GROUP_CONTENT    = 'typesense_search_content';
    public const OPTION_GROUP_FACETS     = 'typesense_search_facetting';

    public const OPTION_REMOTE     = 'typesense_search_remote';
    public const OPTION_INDEX_NAME = 'typesense_search_index_name';
    public const OPTION_ADMIN_KEY  = 'typesense_search_admin_key';
    public const OPTION_SEARCH_KEY = 'typesense_search_search_key';
    public const OPTION_FRONTEND_HOST = 'typesense_search_frontend_host';
    public const OPTION_POST_TYPES = 'typesense_search_post_types';
    public const OPTION_FACETS     = 'typesense_search_facets';
    public const OPTION_HITS_PER_PAGE = 'typesense_search_hits_per_page';
    public const OPTION_INDEX_MODULARITY = 'typesense_index_modularity_content';
    public const OPTION_DEBOUNCE         = 'typesense_search_debounce';
    public const OPTION_DEBOUNCE_DELAY    = 'typesense_search_debounce_delay';

    private static function getTabs(): array
    {
        return [
            'connection' => __('Typesense Connection', 'typesense-search'),
            'content'    => __('Settings', 'typesense-search'),
            'facetting'  => __('Facetting', 'typesense-search'),
            'statistics' => __('Statistics', 'typesense-search'),
        ];
    }

    public function __construct()
    {
        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Register the settings page under WordPress Settings menu.
     */
    public function addSettingsPage(): void
    {
        add_options_page(
            __('Typesense Search', 'typesense-search'),
            __('Typesense Search', 'typesense-search'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    /**
     * Register all plugin settings with WordPress.
     */
    public function registerSettings(): void
    {
        foreach ([
            self::OPTION_REMOTE,
            self::OPTION_INDEX_NAME,
            self::OPTION_ADMIN_KEY,
            self::OPTION_SEARCH_KEY,
            self::OPTION_FRONTEND_HOST,
        ] as $option) {
            register_setting(self::OPTION_GROUP_CONNECTION, $option, [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ]);
        }

        register_setting(self::OPTION_GROUP_CONTENT, self::OPTION_POST_TYPES, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitizePostTypes'],
            'default'           => [],
        ]);

        register_setting(self::OPTION_GROUP_CONTENT, self::OPTION_INDEX_MODULARITY, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]);

        register_setting(self::OPTION_GROUP_CONTENT, self::OPTION_DEBOUNCE, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 1,
        ]);

        register_setting(self::OPTION_GROUP_CONTENT, self::OPTION_DEBOUNCE_DELAY, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 300,
        ]);

        register_setting(self::OPTION_GROUP_CONTENT, self::OPTION_HITS_PER_PAGE, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 10,
        ]);

        register_setting(self::OPTION_GROUP_FACETS, self::OPTION_FACETS, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitizeFacets'],
            'default'           => [],
        ]);
    }

    /**
     * Enqueue admin-only styles and scripts on the settings page.
     */
    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'settings_page_' . self::PAGE_SLUG) {
            return;
        }

        $cssFile = CacheBust::name('css/admin-settings.css') ?: 'css/admin-settings.css';
        $jsFile  = CacheBust::name('js/admin-settings.js')  ?: 'js/admin-settings.js';

        $cssPath = TYPESENSESEARCH_PATH . 'assets/dist/' . $cssFile;
        $jsPath  = TYPESENSESEARCH_PATH . 'assets/dist/' . $jsFile;

        if (file_exists($cssPath)) {
            wp_enqueue_style(
                'typesense-search-admin',
                TYPESENSESEARCH_URL . '/assets/dist/' . $cssFile,
                [],
                null
            );
        }

        if (file_exists($jsPath)) {
            wp_enqueue_script(
                'typesense-search-admin',
                TYPESENSESEARCH_URL . '/assets/dist/' . $jsFile,
                [],
                null,
                true
            );

            wp_localize_script('typesense-search-admin', 'tsAdminI18n', I18n::adminStrings());

            wp_localize_script('typesense-search-admin', 'tsSettings', [
                'ajaxUrl'              => admin_url('admin-ajax.php'),
                'nonce'                => wp_create_nonce(SettingsAjax::AJAX_ACTION_TEST),
                'action'               => SettingsAjax::AJAX_ACTION_TEST,
                'nonceCreateCol'       => wp_create_nonce(SettingsAjax::AJAX_ACTION_CREATE_COL),
                'actionCreateCol'      => SettingsAjax::AJAX_ACTION_CREATE_COL,
                'nonceGenKey'          => wp_create_nonce(SettingsAjax::AJAX_ACTION_GEN_KEY),
                'actionGenKey'         => SettingsAjax::AJAX_ACTION_GEN_KEY,
                'nonceGetStats'        => wp_create_nonce(SettingsAjax::AJAX_ACTION_GET_STATS),
                'actionGetStats'       => SettingsAjax::AJAX_ACTION_GET_STATS,
                'nonceClearType'       => wp_create_nonce(SettingsAjax::AJAX_ACTION_CLEAR_POST_TYPE),
                'actionClearType'      => SettingsAjax::AJAX_ACTION_CLEAR_POST_TYPE,
                'nonceGetFacetFields'  => wp_create_nonce(SettingsAjax::AJAX_ACTION_GET_FACET_FIELDS),
                'actionGetFacetFields' => SettingsAjax::AJAX_ACTION_GET_FACET_FIELDS,
            ]);
        }
    }

    /**
     * Render the settings page, delegating to the view template.
     */
    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $activeTab = isset($_GET['tab']) && array_key_exists($_GET['tab'], self::getTabs())  // phpcs:ignore WordPress.Security.NonceVerification
            ? sanitize_key($_GET['tab'])  // phpcs:ignore WordPress.Security.NonceVerification
            : 'connection';

        $tabs             = self::getTabs();
        $postTypes        = self::getIndexablePostTypes();
        $enabledPostTypes = (array) get_option(self::OPTION_POST_TYPES, []);
        $facets           = (array) get_option(self::OPTION_FACETS, []);
        $hitsPerPage      = (int) get_option(self::OPTION_HITS_PER_PAGE, 10);

        include TYPESENSESEARCH_PATH . 'views/admin/settings-page.php';
    }

    /**
     * Sanitize the post types array before saving.
     */
    public function sanitizePostTypes(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_map('sanitize_key', $value);
    }

    /**
     * Sanitize the facets array before saving.
     * Each facet must have a non-empty 'field' key.
     */
    public function sanitizeFacets(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $field = sanitize_key($item['field'] ?? '');
            if (empty($field)) {
                continue;
            }
            $display = sanitize_text_field($item['display_as'] ?? 'dropdown');
            if (!in_array($display, ['dropdown', 'button_group'], true)) {
                $display = 'dropdown';
            }

            $result[] = [
                'field'       => $field,
                'label'       => sanitize_text_field($item['label'] ?? ''),
                'placeholder' => sanitize_text_field($item['placeholder'] ?? ''),
                'display_as'  => $display,
            ];
        }

        return $result;
    }

    /**
     * Return all public, indexable post types (excluding attachments).
     *
     * @return \WP_Post_Type[]
     */
    public static function getIndexablePostTypes(): array
    {
        $postTypes = get_post_types(['public' => true], 'objects');
        unset($postTypes['attachment']);

        return $postTypes;
    }

    /**
     * Check whether a specific post type is enabled for indexing.
     */
    public static function isPostTypeEnabled(string $postType): bool
    {
        $enabled = (array) get_option(self::OPTION_POST_TYPES, []);

        return in_array($postType, $enabled, true);
    }

    /**
     * Check whether the Modularity plugin is available.
     */
    public static function isModularityAvailable(): bool
    {
        return class_exists('\\Modularity\\App');
    }
}
