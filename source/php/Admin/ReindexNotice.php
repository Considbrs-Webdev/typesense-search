<?php

namespace TypesenseSearch\Admin;

use TypesenseSearch\Admin\Settings\OptionKeys;

/**
 * Warns administrators that indexed URLs are stale after a site setting that
 * feeds every document's `url`/`path_urls` has changed (permalink structure,
 * home/site URL, front page). A full re-index from the settings page fixes it.
 *
 * @package TypesenseSearch\Admin
 */
class ReindexNotice
{
    public const OPTION_FLAG = 'typesense_search_reindex_needed';
    private const DISMISS_ACTION = 'typesense_search_dismiss_reindex_notice';

    /** Options whose change makes indexed URLs stale. */
    private const WATCHED_OPTIONS = [
        'permalink_structure',
        'home',
        'siteurl',
        'page_on_front',
        'show_on_front',
    ];

    public function register(): void
    {
        foreach (self::WATCHED_OPTIONS as $option) {
            add_action('update_option_' . $option, [$this, 'flag']);
        }

        add_action('typesense_search/indexing_run_completed', [$this, 'clearAfterRun'], 10, 2);
        add_action('admin_notices', [$this, 'renderNotice']);
        add_action('admin_post_' . self::DISMISS_ACTION, [$this, 'dismiss']);
    }

    public function flag(): void
    {
        update_option(self::OPTION_FLAG, '1', false);
    }

    /**
     * A manual (admin/CLI) run that finished without failures has refreshed the
     * indexed URLs, so the notice no longer applies. Save-triggered runs only
     * touch a single post and must not clear it.
     */
    public function clearAfterRun(string $source, int $failed): void
    {
        if ($failed === 0 && in_array($source, ['admin', 'cli'], true)) {
            delete_option(self::OPTION_FLAG);
        }
    }

    public function renderNotice(): void
    {
        if (!current_user_can('manage_options') || get_option(self::OPTION_FLAG) !== '1') {
            return;
        }

        $dismissUrl = wp_nonce_url(
            admin_url('admin-post.php?action=' . self::DISMISS_ACTION),
            self::DISMISS_ACTION
        );
        $settingsUrl = admin_url('admin.php?page=' . OptionKeys::PAGE_SLUG);

        printf(
            '<div class="notice notice-warning"><p>%s <a href="%s">%s</a> &middot; <a href="%s">%s</a></p></div>',
            esc_html__(
                'Typesense Search: the permalink structure, site address or front page has changed. Indexed URLs are out of date until the content is re-indexed.',
                'typesense-search'
            ),
            esc_url($settingsUrl),
            esc_html__('Open search settings', 'typesense-search'),
            esc_url($dismissUrl),
            esc_html__('Dismiss', 'typesense-search')
        );
    }

    public function dismiss(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'typesense-search'), 403);
        }
        check_admin_referer(self::DISMISS_ACTION);

        delete_option(self::OPTION_FLAG);

        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }
}
