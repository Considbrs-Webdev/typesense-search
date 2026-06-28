<?php

namespace TypesenseSearch\Admin\Settings;

use TypesenseSearch\Admin\SettingsAjax;
use TypesenseSearch\Frontend\I18n;
use TypesenseSearch\Helper\CacheBust;
use TypesenseSearch\Typesense\ServerCapabilities;

/**
 * Handles settings page registration, asset enqueueing, and page rendering.
 *
 * @package TypesenseSearch\Admin\Settings
 */
class SettingsPage
{
    private static function getTabs(): array
    {
        return [
            'connection'        => __('Typesense Connection', 'typesense-search'),
            'content'           => __('Settings', 'typesense-search'),
            'advanced-settings' => __('Advanced settings', 'typesense-search'),
            'quick-search'      => __('Quick search', 'typesense-search'),
            'statistics'        => __('Statistics', 'typesense-search'),
            'logging'           => __('Logging', 'typesense-search'),
            'status'            => __('Status', 'typesense-search'),
        ];
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
            OptionKeys::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    /**
     * Enqueue admin-only styles and scripts on the settings page.
     */
    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'settings_page_' . OptionKeys::PAGE_SLUG) {
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
                'nonceReindexType'     => wp_create_nonce(SettingsAjax::AJAX_ACTION_REINDEX_POST_TYPE),
                'actionReindexType'    => SettingsAjax::AJAX_ACTION_REINDEX_POST_TYPE,
                'nonceGetFacetFields'  => wp_create_nonce(SettingsAjax::AJAX_ACTION_GET_FACET_FIELDS),
                'actionGetFacetFields' => SettingsAjax::AJAX_ACTION_GET_FACET_FIELDS,
                'nonceCheckStatus'     => wp_create_nonce(SettingsAjax::AJAX_ACTION_CHECK_STATUS),
                'actionCheckStatus'    => SettingsAjax::AJAX_ACTION_CHECK_STATUS,
                'nonceFixSearchKey'    => wp_create_nonce(SettingsAjax::AJAX_ACTION_FIX_SEARCH_KEY),
                'actionFixSearchKey'   => SettingsAjax::AJAX_ACTION_FIX_SEARCH_KEY,
                'nonceStatusCreateCol' => wp_create_nonce(SettingsAjax::AJAX_ACTION_STATUS_CREATE_COL),
                'actionStatusCreateCol'=> SettingsAjax::AJAX_ACTION_STATUS_CREATE_COL,
                'nonceClearLog'        => wp_create_nonce(SettingsAjax::AJAX_ACTION_CLEAR_LOG),
                'actionClearLog'       => SettingsAjax::AJAX_ACTION_CLEAR_LOG,
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
        $postTypes        = \TypesenseSearch\Services\SettingsRepository::getIndexablePostTypes();
        $enabledPostTypes = (array) get_option(OptionKeys::OPTION_POST_TYPES, []);
        $pdfToTextAvailable = \TypesenseSearch\Services\SettingsRepository::isPdfToTextAvailable();
        $facets                = (array) get_option(OptionKeys::OPTION_FACETS, []);
        $hitsPerPage           = (int) get_option(OptionKeys::OPTION_HITS_PER_PAGE, 10);
        $quickSearchEnabled       = (int) get_option(OptionKeys::OPTION_QUICK_SEARCH_ENABLED, 0);
        $quickSearchSelectors     = (array) get_option(OptionKeys::OPTION_QUICK_SEARCH_SELECTORS, []);
        $quickSearchHitsPerPage   = (int) get_option(OptionKeys::OPTION_QUICK_SEARCH_HITS_PER_PAGE, 5);
        $supportsPinnedResults    = $activeTab === 'advanced-settings'
            ? ServerCapabilities::supportsCurationSets()
            : false;

        include TYPESENSESEARCH_PATH . 'views/admin/settings-page.php';
    }
}
