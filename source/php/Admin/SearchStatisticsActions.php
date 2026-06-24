<?php

namespace TypesenseSearch\Admin;

use TypesenseSearch\SearchStatistics\Repository;

/**
 * Handles privileged administration actions for the local statistics table.
 */
class SearchStatisticsActions
{
    public const CLEAR_ACTION = 'typesense_clear_search_statistics';

    public function __construct(private Repository $repository)
    {
        add_action('admin_post_' . self::CLEAR_ACTION, [$this, 'clear']);
    }

    public function clear(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to clear search statistics.', 'typesense-search'));
        }

        check_admin_referer(self::CLEAR_ACTION);
        $deleted = $this->repository->clear();

        if (isset($_POST['typesense_search_log_return']) && '1' === sanitize_text_field(wp_unslash((string) $_POST['typesense_search_log_return']))) { // phpcs:ignore WordPress.Security.NonceVerification
            wp_safe_redirect(add_query_arg([
                'page'                                  => SearchLogPage::PAGE_SLUG,
                'typesense_search_statistics_cleared' => $deleted,
            ], admin_url('tools.php')));
            exit;
        }

        add_settings_error(
            'typesense_search_notices',
            'typesense-search-statistics-cleared',
            self::getClearedMessage($deleted),
            'updated'
        );
        set_transient('settings_errors', get_settings_errors(), 30);

        wp_safe_redirect(add_query_arg([
            'page' => Settings::PAGE_SLUG,
            'tab' => 'statistics',
            'settings-updated' => 'true',
        ], admin_url('options-general.php')));
        exit;
    }

    public static function getClearedMessage(int $deleted): string
    {
        return sprintf(
            /* translators: %d: number of deleted statistics rows */
            _n('%d search statistic was deleted.', '%d search statistics were deleted.', $deleted, 'typesense-search'),
            $deleted
        );
    }
}
