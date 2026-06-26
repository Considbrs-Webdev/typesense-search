<?php

namespace TypesenseSearch\Admin;

use TypesenseSearch\Helper\CacheBust;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Typesense\ServerCapabilities;

/**
 * Renders the JavaScript-based pinned results manager.
 */
class PinnedResultsPage
{
    public const PAGE_SLUG = 'typesense-search-pinned-results';

    public function __construct(private SettingsRepository $settings)
    {
        add_action('admin_menu', [$this, 'addPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addPage(): void
    {
        if (!$this->shouldShow()) {
            return;
        }

        add_options_page(
            __('Pinned search results', 'typesense-search'),
            __('Pinned results', 'typesense-search'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'settings_page_' . self::PAGE_SLUG) {
            return;
        }

        $cssFile = CacheBust::name('css/pinned-results-admin.css') ?: 'css/pinned-results-admin.css';
        $jsFile = CacheBust::name('js/pinned-results-admin.js') ?: 'js/pinned-results-admin.js';

        $cssPath = TYPESENSESEARCH_PATH . 'assets/dist/' . $cssFile;
        $jsPath = TYPESENSESEARCH_PATH . 'assets/dist/' . $jsFile;

        if (file_exists($cssPath)) {
            wp_enqueue_style(
                'typesense-search-pinned-results',
                TYPESENSESEARCH_URL . '/assets/dist/' . $cssFile,
                [],
                null
            );
        }

        if (file_exists($jsPath)) {
            wp_enqueue_script(
                'typesense-search-pinned-results',
                TYPESENSESEARCH_URL . '/assets/dist/' . $jsFile,
                [],
                null,
                true
            );

            wp_localize_script('typesense-search-pinned-results', 'tsPinnedResults', [
                'restUrl' => esc_url_raw(rest_url('typesense-search/v1/pinned-results')),
                'nonce'   => wp_create_nonce('wp_rest'),
            ]);
        }
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!$this->shouldShow()) {
            wp_die(esc_html__('Pinned results are not available.', 'typesense-search'));
        }

        include TYPESENSESEARCH_PATH . 'views/admin/pinned-results-page.php';
    }

    private function shouldShow(): bool
    {
        return $this->settings->isPinnedResultsEnabled()
            && ServerCapabilities::supportsCurationSets();
    }
}
