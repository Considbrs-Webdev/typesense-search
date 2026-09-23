<?php

namespace TypesenseSearch\Admin;

use TypesenseSearch\Admin\Settings\OptionKeys;
use TypesenseSearch\Helper\CacheBust;
use TypesenseSearch\Frontend\I18n;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Typesense\ServerCapabilities;

/**
 * Renders the JavaScript-based pinned results manager.
 */
class PinnedResultsPage
{
    public const PAGE_SLUG = 'typesense-search-pinned-results';

    /**
     * The hook suffix WordPress assigns this submenu page, captured from
     * add_submenu_page()'s return value. The submenu's hook name depends on
     * sanitize_title() of the *translated* top-level menu title, so it must
     * not be hardcoded/guessed — it varies per site locale.
     */
    private string $pageHook = '';

    public function __construct(
        private SettingsRepository $settings,
        private ServerCapabilities $capabilities
    ) {
        add_action('admin_menu', [$this, 'addPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('script_loader_tag', [$this, 'addModuleType'], 10, 2);
    }

    public function addPage(): void
    {
        // Only the setting is checked here: it can only be enabled when the server
        // supported the feature, and this runs on every admin request, so a
        // server version lookup would mean a remote call each time.
        if (!$this->settings->isPinnedResultsEnabled()) {
            return;
        }

        $hook = add_submenu_page(
            OptionKeys::PAGE_SLUG,
            __('Pinned search results', 'typesense-search'),
            __('Pinned results', 'typesense-search'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );

        $this->pageHook = $hook !== false ? $hook : '';
    }

    public function addModuleType(string $tag, string $handle): string
    {
        if ($handle !== 'typesense-search-pinned-results') {
            return $tag;
        }
        return str_replace(' src=', ' type="module" src=', $tag);
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook === '' || $hook !== $this->pageHook) {
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
                'i18n'    => I18n::pinnedResultsStrings(),
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
            && $this->capabilities->supportsCurationSets();
    }
}
