<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Database;

use Brain\Monkey\Functions;
use TypesenseSearch\PinnedResults\Database as PinnedResultsDatabase;
use TypesenseSearch\SearchStatistics\Database as SearchStatisticsDatabase;
use TypesenseSearch\Tests\TestCase;

class MigrationGuardTest extends TestCase
{
    /**
     * @doesNotPerformAssertions — the ->never() expectation is verified by Mockery on tearDown.
     */
    public function test_search_statistics_migration_is_skipped_when_installed_version_is_current(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with(SearchStatisticsDatabase::OPTION_DB_VERSION, '')
            ->andReturn(SearchStatisticsDatabase::DB_VERSION);

        // update_option must never be called when the version is already current.
        // If it were called it would mean migrate() ran despite the guard.
        Functions\expect('update_option')->never();

        SearchStatisticsDatabase::maybeMigrate();
    }

    /**
     * @doesNotPerformAssertions — the ->never() expectation is verified by Mockery on tearDown.
     */
    public function test_pinned_results_migration_is_skipped_when_installed_version_is_current(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with(PinnedResultsDatabase::OPTION_DB_VERSION, '')
            ->andReturn(PinnedResultsDatabase::DB_VERSION);

        Functions\expect('update_option')->never();

        PinnedResultsDatabase::maybeMigrate();
    }
}
