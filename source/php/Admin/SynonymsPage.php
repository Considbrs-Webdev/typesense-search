<?php

namespace TypesenseSearch\Admin;

use TypesenseSearch\Admin\Settings\OptionKeys;
use TypesenseSearch\Helper\CacheBust;
use TypesenseSearch\Frontend\I18n;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Typesense\ServerCapabilities;

/**
 * Renders the JavaScript-based synonyms manager.
 */
class SynonymsPage
{
    public const PAGE_SLUG = 'typesense-search-synonyms';

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
        if (!$this->shouldShow()) {
            return;
        }

        $hook = add_submenu_page(
            OptionKeys::PAGE_SLUG,
            __('Synonyms', 'typesense-search'),
            __('Synonyms', 'typesense-search'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );

        $this->pageHook = $hook !== false ? $hook : '';
    }

    public function addModuleType(string $tag, string $handle): string
    {
        if ($handle !== 'typesense-search-synonyms') {
            return $tag;
        }
        return str_replace(' src=', ' type="module" src=', $tag);
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook === '' || $hook !== $this->pageHook) {
            return;
        }

        $cssFile = CacheBust::name('css/synonyms-admin.css') ?: 'css/synonyms-admin.css';
        $jsFile = CacheBust::name('js/synonyms-admin.js') ?: 'js/synonyms-admin.js';

        $cssPath = TYPESENSESEARCH_PATH . 'assets/dist/' . $cssFile;
        $jsPath = TYPESENSESEARCH_PATH . 'assets/dist/' . $jsFile;

        if (file_exists($cssPath)) {
            wp_enqueue_style(
                'typesense-search-synonyms',
                TYPESENSESEARCH_URL . '/assets/dist/' . $cssFile,
                [],
                null
            );
        }

        if (file_exists($jsPath)) {
            wp_enqueue_script(
                'typesense-search-synonyms',
                TYPESENSESEARCH_URL . '/assets/dist/' . $jsFile,
                [],
                null,
                true
            );

            wp_localize_script('typesense-search-synonyms', 'tsSynonyms', [
                'restUrl' => esc_url_raw(rest_url('typesense-search/v1/synonyms')),
                'nonce'   => wp_create_nonce('wp_rest'),
                'i18n'    => I18n::synonymsStrings(),
            ]);
        }
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!$this->shouldShow()) {
            wp_die(esc_html__('Synonyms are not available.', 'typesense-search'));
        }

        include TYPESENSESEARCH_PATH . 'views/admin/synonyms-page.php';
    }

    private function shouldShow(): bool
    {
        return $this->settings->isSynonymsEnabled()
            && $this->capabilities->supportsSynonymSets();
    }
}
