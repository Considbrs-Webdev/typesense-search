<?php

namespace TypesenseSearch\CLI\Actions;

use TypesenseSearch\SearchStatistics\Repository as SearchStatisticsRepository;
use TypesenseSearch\Services\SettingsRepository;

/**
 * Handles the `wp typesense prune-search-statistics` and
 * `wp typesense populate-search-log` subcommands.
 *
 * @package TypesenseSearch\CLI\Actions
 */
class StatisticsAction
{
    public function __construct(
        private SettingsRepository $settings,
        private SearchStatisticsRepository $searchStatistics
    ) {
    }

    /**
     * Remove search-statistics rows older than the configured retention period.
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assocArgs
     */
    public function pruneSearchStatistics(array $args, array $assocArgs): void
    {
        $days = \WP_CLI\Utils\get_flag_value($assocArgs, 'days', $this->settings->getSearchStatisticsRetentionDays());
        $days = max(1, (int) $days);
        $deleted = $this->searchStatistics->prune($days);

        \WP_CLI::success(sprintf(
            'Deleted %d search statistic%s older than %d day%s.',
            $deleted,
            $deleted === 1 ? '' : 's',
            $days,
            $days === 1 ? '' : 's'
        ));
    }

    /**
     * Add sample search-log entries for testing pagination and filters.
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assocArgs
     */
    public function populateSearchLog(array $args, array $assocArgs): void
    {
        $count = min(10000, max(1, (int) \WP_CLI\Utils\get_flag_value($assocArgs, 'count', 100)));
        $repeatPercent = min(100, max(0, (int) \WP_CLI\Utils\get_flag_value($assocArgs, 'repeat-percent', 70)));
        $terms = [
            'bygglov',
            'förskola',
            'parkering',
            'återvinning',
            'fritidsaktiviteter',
            'bostadsanpassning',
            'bibliotek',
            'lediga jobb',
            'serveringstillstånd',
            'snöröjning',
        ];
        $created = 0;

        for ($index = 0; $index < $count; $index++) {
            $baseTerm = $terms[$index % count($terms)];
            $term = ($index % 100) < $repeatPercent
                ? $baseTerm
                : $baseTerm . ' test ' . $index;
            $sessionId = str_replace('-', '', wp_generate_uuid4());
            $found = $index % 7 === 0 ? 0 : (($index % 200) + 1);
            $surface = $index % 3 === 0 ? 'quick' : 'regular';

            if ($this->searchStatistics->record($term, $found, $surface, $sessionId)) {
                $created++;
            }
        }

        \WP_CLI::success(sprintf(
            'Added %d sample search-log entr%s (%d%% repeated terms).',
            $created,
            $created === 1 ? 'y' : 'ies',
            $repeatPercent
        ));
    }
}
