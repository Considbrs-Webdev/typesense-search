<?php

namespace TypesenseSearch\Bootstrap;

use TypesenseSearch\Admin;
use TypesenseSearch\SearchStatistics\Database as SearchStatisticsDatabase;
use TypesenseSearch\SearchStatistics\DashboardWidgets;
use TypesenseSearch\SearchStatistics\Repository as SearchStatisticsRepository;
use TypesenseSearch\SearchStatistics\RestController as SearchStatisticsRestController;
use TypesenseSearch\SearchStatistics\Retention as SearchStatisticsRetention;
use TypesenseSearch\Services\SettingsRepository;

/**
 * Wires the search-statistics subsystem.
 *
 * Keeps the schema migration and retention lifecycle active even when
 * logging is currently switched off.
 *
 * @package TypesenseSearch\Bootstrap
 */
class SearchStatisticsFeature
{
    private SearchStatisticsRepository $repository;

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function register(): void
    {
        add_action('plugins_loaded', [SearchStatisticsDatabase::class, 'maybeMigrate']);

        $this->repository = new SearchStatisticsRepository();

        new SearchStatisticsRestController($this->settings, $this->repository);
        new SearchStatisticsRetention($this->settings, $this->repository);
        new DashboardWidgets($this->settings, $this->repository);
        new Admin\SearchStatisticsActions($this->repository);
        new Admin\SearchLogPage($this->repository);
    }

    /**
     * Return the shared statistics repository for use by other features (e.g. CLI).
     */
    public function getRepository(): SearchStatisticsRepository
    {
        return $this->repository;
    }
}
