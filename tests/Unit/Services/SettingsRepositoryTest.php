<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Services;

use Brain\Monkey\Functions;
use TypesenseSearch\Admin\Settings;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Tests\TestCase;

class SettingsRepositoryTest extends TestCase
{
    private SettingsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new SettingsRepository();
    }

    public function test_get_hits_per_page_never_returns_less_than_one(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with(Settings::OPTION_HITS_PER_PAGE, 10)
            ->andReturn(0);

        self::assertSame(1, $this->repository->getHitsPerPage());
    }

    public function test_get_sort_display_falls_back_to_radio_for_unknown_values(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with(Settings::OPTION_SORT_DISPLAY, 'radio')
            ->andReturn('tiles');

        self::assertSame('radio', $this->repository->getSortDisplay());
    }

    public function test_get_query_by_weights_returns_typesense_order_with_clamped_values(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with(Settings::OPTION_QUERY_BY_WEIGHTS, [])
            ->andReturn([
                'title'       => 7,
                'excerpt'     => 0,
                'content'     => 3,
                'extra_terms' => 4,
                'type_name'   => 2,
            ]);

        self::assertSame('5,1,3,4,2', $this->repository->getQueryByWeights());
    }

    public function test_search_logging_delay_is_returned_as_clamped_milliseconds(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with(Settings::OPTION_SEARCH_LOGGING_DELAY_SECONDS, 1)
            ->andReturn(40);

        self::assertSame(30000, $this->repository->getSearchLoggingDelayMilliseconds());
    }

    public function test_search_logging_minimum_characters_is_clamped(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with(Settings::OPTION_SEARCH_LOGGING_MINIMUM_CHARACTERS, 3)
            ->andReturn(0);

        self::assertSame(1, $this->repository->getSearchLoggingMinimumCharacters());
    }

    public function test_search_statistics_retention_days_is_clamped(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with(Settings::OPTION_SEARCH_STATISTICS_RETENTION_DAYS, 90)
            ->andReturn(9999);

        self::assertSame(3650, $this->repository->getSearchStatisticsRetentionDays());
    }
}
