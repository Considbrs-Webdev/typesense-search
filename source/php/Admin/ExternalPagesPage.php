<?php

namespace TypesenseSearch\Admin;

use TypesenseSearch\Admin\Settings\OptionKeys;
use TypesenseSearch\Helper\CacheBust;
use TypesenseSearch\Frontend\I18n;
use TypesenseSearch\Services\SettingsRepository;

/**
 * Renders the JavaScript-based external pages manager.
 */
class ExternalPagesPage
{
    public const PAGE_SLUG = 'typesense-search-external-pages';

    /**
     * The hook suffix WordPress assigns this submenu page, captured from
     * add_submenu_page()'s return value. The submenu's hook name depends on
     * sanitize_title() of the *translated* top-level menu title, so it must
     * not be hardcoded/guessed — it varies per site locale.
     */
    private string $pageHook = '';

    public function __construct(private SettingsRepository $settings)
    {
        add_action('admin_menu', [$this, 'addPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('script_loader_tag', [$this, 'addModuleType'], 10, 2);
    }

    public function addPage(): void
    {
        if (!$this->shouldShow()) {
            return;
        }

        $hook = add_submenu_page(
            OptionKeys::PAGE_SLUG,
            __('External pages', 'typesense-search'),
            __('External pages', 'typesense-search'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );

        $this->pageHook = $hook !== false ? $hook : '';
    }

    public function addModuleType(string $tag, string $handle): string
    {
        if ($handle !== 'typesense-search-external-pages') {
            return $tag;
        }
        return str_replace(' src=', ' type="module" src=', $tag);
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook === '' || $hook !== $this->pageHook) {
            return;
        }

        $cssFile = CacheBust::name('css/external-pages-admin.css') ?: 'css/external-pages-admin.css';
        $jsFile = CacheBust::name('js/external-pages-admin.js') ?: 'js/external-pages-admin.js';

        $cssPath = TYPESENSESEARCH_PATH . 'assets/dist/' . $cssFile;
        $jsPath = TYPESENSESEARCH_PATH . 'assets/dist/' . $jsFile;

        if (file_exists($cssPath)) {
            wp_enqueue_style(
                'typesense-search-external-pages',
                TYPESENSESEARCH_URL . '/assets/dist/' . $cssFile,
                [],
                null
            );
        }

        if (file_exists($jsPath)) {
            wp_enqueue_script(
                'typesense-search-external-pages',
                TYPESENSESEARCH_URL . '/assets/dist/' . $jsFile,
                [],
                null,
                true
            );

            wp_localize_script('typesense-search-external-pages', 'tsExternalPages', [
                'restUrl' => esc_url_raw(rest_url('typesense-search/v1/external-pages')),
                'nonce'   => wp_create_nonce('wp_rest'),
                'i18n'    => I18n::externalPagesStrings(),
            ]);
        }
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!$this->shouldShow()) {
            wp_die(esc_html__('External pages are not available.', 'typesense-search'));
        }

        include TYPESENSESEARCH_PATH . 'views/admin/external-pages-page.php';
    }

    private function shouldShow(): bool
    {
        return $this->settings->isExternalPagesEnabled();
    }
}
