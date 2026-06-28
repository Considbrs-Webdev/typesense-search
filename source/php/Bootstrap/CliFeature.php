<?php

namespace TypesenseSearch\Bootstrap;

use TypesenseSearch\CLI\IndexCommand;
use TypesenseSearch\SearchStatistics\Repository as SearchStatisticsRepository;
use TypesenseSearch\Services\SettingsRepository;

/**
 * Registers WP-CLI commands when the CLI environment is detected.
 *
 * @package TypesenseSearch\Bootstrap
 */
class CliFeature
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SearchStatisticsRepository $searchStatistics
    ) {
    }

    public function register(): void
    {
        if (defined('WP_CLI') && constant('WP_CLI') === true) {
            \WP_CLI::add_command('typesense', new IndexCommand($this->settings, $this->searchStatistics));
        }
    }
}
