<?php

namespace TypesenseSearch\Admin;

use TypesenseSearch\SearchStatistics\Repository;

/**
 * Tools → Search log page.
 */
class SearchLogPage
{
    public const PAGE_SLUG = 'typesense-search-log';

    public function __construct(private Repository $repository)
    {
        add_action('admin_menu', [$this, 'register']);
        add_filter('set-screen-option', [$this, 'saveScreenOption'], 10, 3);
    }

    public function register(): void
    {
        $hook = add_management_page(
            __('Search log', 'typesense-search'),
            __('Search log', 'typesense-search'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render']
        );
        add_action("load-{$hook}", [$this, 'load']);
    }

    public function load(): void
    {
        add_screen_option('per_page', [
            'label'   => __('Searches per page', 'typesense-search'),
            'default' => 20,
            'option'  => 'typesense_search_log_per_page',
        ]);
    }

    public function saveScreenOption(mixed $status, string $option, mixed $value): mixed
    {
        if ($option !== 'typesense_search_log_per_page') {
            return $status;
        }

        return min(100, max(1, absint($value)));
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        $table = new SearchLogListTable($this->repository);
        $table->prepare_items();
        $clearedStatistics = isset($_GET['typesense_search_statistics_cleared'])
            ? absint($_GET['typesense_search_statistics_cleared'])
            : null; // phpcs:ignore WordPress.Security.NonceVerification
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Search log', 'typesense-search'); ?></h1>
            <p><?php esc_html_e('Each row represents one normalised search term per anonymous browser session. Repeated searches in the same session update when it was last searched without increasing the count.', 'typesense-search'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Clear all stored search statistics? This cannot be undone.', 'typesense-search')); ?>');">
                <input type="hidden" name="action" value="<?php echo esc_attr(SearchStatisticsActions::CLEAR_ACTION); ?>" />
                <input type="hidden" name="typesense_search_log_return" value="1" />
                <?php wp_nonce_field(SearchStatisticsActions::CLEAR_ACTION); ?>
                <button type="submit" class="button button-secondary"><?php esc_html_e('Clear statistics', 'typesense-search'); ?></button>
            </form>

            <?php if ($clearedStatistics !== null) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html(SearchStatisticsActions::getClearedMessage($clearedStatistics)); ?></p></div>
            <?php endif; ?>

            <?php if ($table->getDeletedItems() > 0) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html(sprintf(_n('%d search was deleted.', '%d searches were deleted.', $table->getDeletedItems(), 'typesense-search'), $table->getDeletedItems())); ?></p></div>
            <?php endif; ?>

            <ul class="subsubsub">
                <?php foreach ($table->getModeLinks() as $mode => $link) : ?>
                    <li class="<?php echo esc_attr($mode); ?>"><?php echo wp_kses_post($link); ?><?php echo $mode === 'events' ? ' |' : ''; ?></li>
                <?php endforeach; ?>
            </ul>
            <br class="clear" />

            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>" />
                <input type="hidden" name="mode" value="<?php echo esc_attr(isset($_GET['mode']) && sanitize_key((string) $_GET['mode']) === 'grouped' ? 'grouped' : 'events'); ?>" /><?php // phpcs:ignore WordPress.Security.NonceVerification ?>
                <input type="hidden" name="status" value="<?php echo esc_attr(isset($_GET['status']) ? sanitize_key((string) $_GET['status']) : 'all'); ?>" /><?php // phpcs:ignore WordPress.Security.NonceVerification ?>
                <?php $table->search_box(__('Search terms', 'typesense-search'), 'typesense-search-log'); ?>
                <?php $table->display(); ?>
            </form>
        </div>
        <?php
    }
}
