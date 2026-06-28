<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Database;

use Brain\Monkey\Functions;
use TypesenseSearch\PinnedResults\Database as PinnedResultsDatabase;
use TypesenseSearch\SearchStatistics\Database as SearchStatisticsDatabase;
use TypesenseSearch\Tests\TestCase;

class MigrationGuardTest extends TestCase
{
    public function test_search_statistics_migration_is_skipped_when_installed_version_is_current(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with(SearchStatisticsDatabase::OPTION_DB_VERSION, '')
            ->andReturn(SearchStatisticsDatabase::DB_VERSION);

        SearchStatisticsDatabase::maybeMigrate();

        self::assertTrue(true);
    }

    public function test_pinned_results_migration_is_skipped_when_installed_version_is_current(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with(PinnedResultsDatabase::OPTION_DB_VERSION, '')
            ->andReturn(PinnedResultsDatabase::DB_VERSION);

        PinnedResultsDatabase::maybeMigrate();

        self::assertTrue(true);
    }
}
