<?php

namespace TypesenseSearch\Frontend;

use TypesenseSearch\Helper\CacheBust;
use TypesenseSearch\Frontend\I18n;

/**
 * Class Assets
 *
 * Enqueues frontend styles and scripts for the search page.
 *
 * @package TypesenseSearch\Frontend
 */
class Assets
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueStyles']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueQuickSearchStyles']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueQuickSearchScripts']);
        add_filter('script_loader_tag',  [$this, 'addModuleType'], 10, 2);
    }

    /**
     * Add type="module" to the Typesense script tags so browsers accept the
     * ES module syntax that Vite outputs (including shared chunk imports).
     */
    public function addModuleType(string $tag, string $handle): string
    {
        $handles = ['typesense-search', 'typesense-quick-search'];
        if (!in_array($handle, $handles, true)) {
            return $tag;
        }
        return str_replace(' src=', ' type="module" src=', $tag);
    }

    /**
     * Enqueue the compiled search script on search pages.
     */
    public function enqueueScripts(): void
    {
        if (!is_search()) {
            return;
        }

        $jsFile = CacheBust::name('js/typesense-search.js') ?: 'js/typesense-search.js';
        $jsPath = TYPESENSESEARCH_PATH . 'assets/dist/' . $jsFile;

        if (!file_exists($jsPath)) {
            return;
        }

        wp_enqueue_script(
            'typesense-search',
            TYPESENSESEARCH_URL . '/assets/dist/' . $jsFile,
            [],
            null,
            true
        );

        wp_localize_script(
            'typesense-search',
            'typesenseI18n',
            I18n::strings()
        );
    }

    /**
     * Enqueue the compiled search stylesheet on search pages.
     */
    public function enqueueStyles(): void
    {
        if (!is_search()) {
            return;
        }

        $cssFile = CacheBust::name('css/typesense-search.css') ?: 'css/typesense-search.css';
        $cssPath = TYPESENSESEARCH_PATH . 'assets/dist/' . $cssFile;

        if (!file_exists($cssPath)) {
            return;
        }

        wp_enqueue_style(
            'typesense-search',
            TYPESENSESEARCH_URL . '/assets/dist/' . $cssFile,
            [],
            null
        );
    }

    /**
     * Enqueue the quick search script on all pages when the feature is enabled.
     */
    public function enqueueQuickSearchScripts(): void
    {
        if (!get_option(\TypesenseSearch\Admin\Settings::OPTION_QUICK_SEARCH_ENABLED, 0)) {
            return;
        }

        $jsFile = CacheBust::name('js/quick-search.js') ?: 'js/quick-search.js';
        $jsPath = TYPESENSESEARCH_PATH . 'assets/dist/' . $jsFile;

        if (!file_exists($jsPath)) {
            return;
        }

        wp_enqueue_script(
            'typesense-quick-search',
            TYPESENSESEARCH_URL . '/assets/dist/' . $jsFile,
            [],
            null,
            true
        );

        wp_localize_script(
            'typesense-quick-search',
            'typesenseQuickSearchI18n',
            I18n::quickSearchStrings()
        );
    }

    /**
     * Enqueue the quick search stylesheet on all pages when the feature is enabled.
     */
    public function enqueueQuickSearchStyles(): void
    {
        if (!get_option(\TypesenseSearch\Admin\Settings::OPTION_QUICK_SEARCH_ENABLED, 0)) {
            return;
        }

        $cssFile = CacheBust::name('css/quick-search.css') ?: 'css/quick-search.css';
        $cssPath = TYPESENSESEARCH_PATH . 'assets/dist/' . $cssFile;

        if (!file_exists($cssPath)) {
            return;
        }

        wp_enqueue_style(
            'typesense-quick-search',
            TYPESENSESEARCH_URL . '/assets/dist/' . $cssFile,
            [],
            null
        );
    }
}
