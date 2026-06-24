<?php

namespace TypesenseSearch\Admin;

use TypesenseSearch\SearchStatistics\Repository;

/**
 * Standard WordPress list table for the locally retained search log.
 */
class SearchLogListTable extends \WP_List_Table
{
    private string $mode;
    private string $status;
    private string $context;
    private string $search;
    private int $deletedItems = 0;

    public function __construct(private Repository $repository)
    {
        parent::__construct([
            'singular' => 'search',
            'plural'   => 'searches',
            'ajax'     => false,
        ]);

        $this->mode = (isset($_GET['mode']) && sanitize_key((string) $_GET['mode']) === 'grouped') ? 'grouped' : 'events'; // phpcs:ignore WordPress.Security.NonceVerification
        $this->status = isset($_GET['status']) ? sanitize_key((string) $_GET['status']) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification
        $this->context = isset($_GET['context']) ? sanitize_key((string) $_GET['context']) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification
        $this->search = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    }

    /** @return array<string, string> */
    public function get_columns(): array
    {
        $columns = [
            'term'          => __('Term', 'typesense-search'),
            'last_searched' => $this->mode === 'events'
                ? __('Date', 'typesense-search')
                : __('When last searched', 'typesense-search'),
            'context'       => __('Context', 'typesense-search'),
        ];

        if ($this->mode === 'events') {
            $columns['hits'] = __('Hits', 'typesense-search');
            return ['cb' => '<input type="checkbox" />'] + $columns;
        }

        $columns['count'] = __('Searches', 'typesense-search');
        return $columns;
    }

    /** @return array<string, array{0: string, 1: bool}> */
    protected function get_sortable_columns(): array
    {
        $columns = [
            'term'          => ['term', false],
            'last_searched' => ['last', true],
        ];

        if ($this->mode === 'grouped') {
            $columns['count'] = ['count', false];
        } else {
            $columns['hits'] = ['hits', false];
        }

        return $columns;
    }

    /** @return array<string, string> */
    protected function get_views(): array
    {
        $views = [
            'all'     => __('All', 'typesense-search'),
            'hits'    => __('With hits', 'typesense-search'),
            'no-hits' => __('Without hits', 'typesense-search'),
        ];
        $links = [];
        foreach ($views as $status => $label) {
            $url = $this->url(['status' => $status, 'paged' => false]);
            $links[$status] = sprintf(
                '<a href="%1$s"%2$s>%3$s</a>',
                esc_url($url),
                $this->status === $status ? ' class="current" aria-current="page"' : '',
                esc_html($label)
            );
        }

        return $links;
    }

    /** @return array<string, string> */
    public function getModeLinks(): array
    {
        $modes = [
            'events'  => __('Searches', 'typesense-search'),
            'grouped' => __('Grouped by term', 'typesense-search'),
        ];
        $links = [];
        foreach ($modes as $mode => $label) {
            $url = $this->url(['mode' => $mode, 'paged' => false]);
            $links[$mode] = sprintf(
                '<a href="%1$s"%2$s>%3$s</a>',
                esc_url($url),
                $this->mode === $mode ? ' class="current" aria-current="page"' : '',
                esc_html($label)
            );
        }

        return $links;
    }

    public function prepare_items(): void
    {
        $this->processBulkAction();
        $perPage = $this->get_items_per_page('typesense_search_log_per_page', 20);
        $orderby = isset($_GET['orderby']) ? sanitize_key((string) $_GET['orderby']) : 'last'; // phpcs:ignore WordPress.Security.NonceVerification
        $order = isset($_GET['order']) ? sanitize_key((string) $_GET['order']) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification
        $log = $this->repository->getSearchLog(
            $this->mode,
            $this->status,
            $this->context,
            $this->search,
            $orderby,
            $order,
            $perPage,
            $this->get_pagenum()
        );

        $this->items = $log['items'];
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
        $this->set_pagination_args([
            'total_items' => $log['total'],
            'per_page'    => $perPage,
        ]);
    }

    /** @param array<string, mixed> $item */
    public function column_term(array $item): string
    {
        return '<strong>' . esc_html((string) $item['query_text']) . '</strong>';
    }

    /** @param array<string, mixed> $item */
    public function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="search_ids[]" value="%d" />', (int) $item['id']);
    }

    /** @param array<string, mixed> $item */
    public function column_last_searched(array $item): string
    {
        $format = get_option('date_format') . ' ' . get_option('time_format');
        return esc_html(wp_date($format, strtotime((string) $item['last_searched_at'] . ' UTC')));
    }

    /** @param array<string, mixed> $item */
    public function column_context(array $item): string
    {
        $contexts = $this->mode === 'grouped'
            ? explode(',', (string) $item['contexts'])
            : [(string) $item['last_surface']];

        return esc_html(implode(', ', array_map([$this, 'contextLabel'], $contexts)));
    }

    /** @param array<string, mixed> $item */
    public function column_count(array $item): string
    {
        return esc_html(number_format_i18n((int) $item['total_searches']));
    }

    /** @param array<string, mixed> $item */
    public function column_hits(array $item): string
    {
        return esc_html(number_format_i18n((int) $item['last_found']));
    }

    /** @return array<string, string> */
    protected function get_bulk_actions(): array
    {
        return $this->mode === 'events'
            ? ['delete' => __('Delete', 'typesense-search')]
            : [];
    }

    /**
     * Core renders a bulk-actions wrapper even when it has no actions. Avoid
     * that empty wrapper in grouped mode so no stray separator precedes the
     * context selector.
     */
    protected function display_tablenav($which) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ($this->mode !== 'grouped') {
            parent::display_tablenav($which);
            return;
        }
        if ($which === 'bottom' && !$this->has_items()) {
            return;
        }
        ?>
        <div class="tablenav <?php echo esc_attr($which); ?>">
            <?php $this->extra_tablenav($which); ?>
            <?php $this->pagination($which); ?>
            <br class="clear" />
        </div>
        <?php
    }

    public function no_items(): void
    {
        esc_html_e('No searches found.', 'typesense-search');
    }

    public function getDeletedItems(): int
    {
        return $this->deletedItems;
    }

    public function extra_tablenav($which): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ($which !== 'top') {
            return;
        }
        ?>
        <div class="alignleft actions">
            <label class="screen-reader-text" for="typesense-search-log-context"><?php esc_html_e('Filter by context', 'typesense-search'); ?></label>
            <select name="context" id="typesense-search-log-context">
                <option value="all" <?php selected($this->context, 'all'); ?>><?php esc_html_e('All contexts', 'typesense-search'); ?></option>
                <option value="regular" <?php selected($this->context, 'regular'); ?>><?php esc_html_e('Normal search', 'typesense-search'); ?></option>
                <option value="quick" <?php selected($this->context, 'quick'); ?>><?php esc_html_e('Quick search', 'typesense-search'); ?></option>
            </select>
            <?php submit_button(__('Filter', 'typesense-search'), '', 'filter_action', false); ?>
        </div>
        <?php
    }

    private function contextLabel(string $context): string
    {
        return match ($context) {
            'quick' => __('Quick search', 'typesense-search'),
            'regular' => __('Normal search', 'typesense-search'),
            default => $context,
        };
    }

    private function processBulkAction(): void
    {
        if ($this->mode !== 'events' || $this->current_action() !== 'delete') {
            return;
        }

        check_admin_referer('bulk-searches');
        // The list table's form uses GET so filters, sorting and pagination
        // remain shareable. Read selected rows from the request accordingly.
        $ids = isset($_REQUEST['search_ids']) && is_array($_REQUEST['search_ids'])
            ? array_map('absint', wp_unslash($_REQUEST['search_ids']))
            : [];
        $this->deletedItems = $this->repository->deleteSearchLogEntries($ids);
    }

    /** @param array<string, string|false> $changes */
    private function url(array $changes): string
    {
        $args = [
            'page'    => SearchLogPage::PAGE_SLUG,
            'mode'    => $this->mode,
            'status'  => $this->status,
            'context' => $this->context,
        ];
        if ($this->search !== '') {
            $args['s'] = $this->search;
        }
        foreach ($changes as $key => $value) {
            if ($value === false) {
                unset($args[$key]);
            } else {
                $args[$key] = $value;
            }
        }

        return add_query_arg($args, admin_url('tools.php'));
    }
}
