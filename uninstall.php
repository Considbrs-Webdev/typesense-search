<?php

/**
 * Remove local plugin data when WordPress uninstalls the plugin. This file is
 * intentionally self-contained so it can run after the normal plugin bootstrap
 * is unavailable.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/** @param int|null $blogId */
function typesense_search_uninstall_statistics_for_site(?int $blogId = null): void
{
    global $wpdb;

    if ($blogId !== null) {
        switch_to_blog($blogId);
    }

    $table = $wpdb->prefix . 'typesense_search_events';
    $wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

    $pinnedResultsTable = $wpdb->prefix . 'typesense_pinned_results';
    $wpdb->query("DROP TABLE IF EXISTS {$pinnedResultsTable}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

    foreach ([
        'typesense_search_statistics_db_version',
        'typesense_search_pinned_results_db_version',
        'typesense_search_pinned_results_enabled',
        'typesense_search_logging_enabled',
        'typesense_search_logging_dashboard_widgets',
        'typesense_search_logging_require_consent',
        'typesense_search_logging_delay_seconds',
        'typesense_search_logging_minimum_characters',
        'typesense_search_statistics_retention_days',
    ] as $option) {
        delete_option($option);
    }
    wp_clear_scheduled_hook('typesense_search_prune_statistics');

    if ($blogId !== null) {
        restore_current_blog();
    }
}

if (is_multisite()) {
    $siteIds = get_sites(['fields' => 'ids', 'number' => 0]);
    foreach ($siteIds as $siteId) {
        typesense_search_uninstall_statistics_for_site((int) $siteId);
    }
} else {
    typesense_search_uninstall_statistics_for_site();
}
