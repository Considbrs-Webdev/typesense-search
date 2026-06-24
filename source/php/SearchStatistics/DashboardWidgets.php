<?php

namespace TypesenseSearch\SearchStatistics;

use TypesenseSearch\Services\SettingsRepository;

/**
 * Registers the WordPress dashboard widgets for search statistics.
 */
class DashboardWidgets
{
    public function __construct(private SettingsRepository $settings, private Repository $repository)
    {
        add_action('wp_dashboard_setup', [$this, 'register']);
    }

    public function register(): void
    {
        if (!$this->settings->isSearchLoggingEnabled() || !$this->settings->areSearchStatisticsDashboardWidgetsEnabled()) {
            return;
        }

        wp_add_dashboard_widget('typesense_search_latest', __('Latest searches', 'typesense-search'), [$this, 'renderLatest']);
        wp_add_dashboard_widget('typesense_search_failed', __('Failed searches', 'typesense-search'), [$this, 'renderFailed']);
        wp_add_dashboard_widget('typesense_search_popular', __('Popular searches', 'typesense-search'), [$this, 'renderPopular']);
    }

    public function renderLatest(): void
    {
        $this->renderLatestTable($this->repository->getWidgetData()['latest']);
    }

    public function renderFailed(): void
    {
        $this->renderSessionsTable($this->repository->getWidgetData()['failed'], __('No failed searches yet.', 'typesense-search'));
    }

    public function renderPopular(): void
    {
        $this->renderSessionsTable($this->repository->getWidgetData()['popular'], __('No searches recorded yet.', 'typesense-search'));
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function renderLatestTable(array $rows): void
    {
        if (empty($rows)) {
            echo '<p>' . esc_html__('No searches recorded yet.', 'typesense-search') . '</p>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th scope="col">' . esc_html__('Term', 'typesense-search') . '</th>';
        echo '<th scope="col">' . esc_html__('Hits', 'typesense-search') . '</th>';
        echo '<th scope="col">' . esc_html__('When', 'typesense-search') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            printf(
                '<tr><td><strong>%1$s</strong></td><td>%2$s</td><td>%3$s</td></tr>',
                esc_html((string) $row['query_text']),
                esc_html(number_format_i18n((int) $row['found'])),
                esc_html(human_time_diff(strtotime((string) $row['searched_at'] . ' UTC')) . ' ' . __('ago', 'typesense-search'))
            );
        }
        echo '</tbody></table>';
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function renderSessionsTable(array $rows, string $emptyMessage): void
    {
        if (empty($rows)) {
            echo '<p>' . esc_html($emptyMessage) . '</p>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th scope="col">' . esc_html__('Term', 'typesense-search') . '</th>';
        echo '<th scope="col">' . esc_html__('Sessions', 'typesense-search') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            printf(
                '<tr><td><strong>%1$s</strong></td><td>%2$s</td></tr>',
                esc_html((string) $row['query_text']),
                esc_html(number_format_i18n((int) $row['count']))
            );
        }
        echo '</tbody></table>';
    }
}
