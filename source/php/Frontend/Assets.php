<?php

namespace TypesenseSearch\Frontend;

use TypesenseSearch\Helper\CacheBust;

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
}
