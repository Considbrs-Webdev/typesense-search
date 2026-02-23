<?php

namespace TypesenseSearch\Admin;

use TypesenseSearch\Helper\CacheBust;

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

    public const OPTION_REMOTE     = 'typesense_search_remote';
    public const OPTION_INDEX_NAME = 'typesense_search_index_name';
    public const OPTION_ADMIN_KEY  = 'typesense_search_admin_key';
    public const OPTION_SEARCH_KEY = 'typesense_search_search_key';
    public const OPTION_POST_TYPES = 'typesense_search_post_types';

    private const TABS = [
        'connection' => 'Typesense Connection',
        'content'    => 'Content',
    ];

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

            wp_localize_script('typesense-search-admin', 'tsSettings', [
                'ajaxUrl'           => admin_url('admin-ajax.php'),
                'nonce'             => wp_create_nonce(SettingsAjax::AJAX_ACTION_TEST),
                'action'            => SettingsAjax::AJAX_ACTION_TEST,
                'nonceCreateCol'    => wp_create_nonce(SettingsAjax::AJAX_ACTION_CREATE_COL),
                'actionCreateCol'   => SettingsAjax::AJAX_ACTION_CREATE_COL,
                'nonceGenKey'       => wp_create_nonce(SettingsAjax::AJAX_ACTION_GEN_KEY),
                'actionGenKey'      => SettingsAjax::AJAX_ACTION_GEN_KEY,
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

        $activeTab = isset($_GET['tab']) && array_key_exists($_GET['tab'], self::TABS)  // phpcs:ignore WordPress.Security.NonceVerification
            ? sanitize_key($_GET['tab'])  // phpcs:ignore WordPress.Security.NonceVerification
            : 'connection';

        $tabs             = self::TABS;
        $postTypes        = self::getIndexablePostTypes();
        $enabledPostTypes = (array) get_option(self::OPTION_POST_TYPES, []);

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
}
