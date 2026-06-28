<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Bootstrap;

use Mockery;
use TypesenseSearch\Bootstrap\CliFeature;
use TypesenseSearch\SearchStatistics\Repository as SearchStatisticsRepository;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Tests\TestCase;

/**
 * Characterization tests for CliFeature.
 *
 * The feature only registers a WP-CLI command when the WP_CLI constant is
 * defined and truthy. These tests cover the no-op branch (WP_CLI absent) so
 * that the class can be exercised without a full CLI environment.
 */
class CliFeatureTest extends TestCase
{
    public function test_register_does_nothing_when_wp_cli_is_not_defined(): void
    {
        // Arrange: WP_CLI constant must not be defined for this assertion path.
        self::assertFalse(defined('WP_CLI'), 'WP_CLI should not be defined in the unit test environment');

        $settings        = Mockery::mock(SettingsRepository::class);
        $searchStatistics = Mockery::mock(SearchStatisticsRepository::class);

        $feature = new CliFeature($settings, $searchStatistics);

        // Act + Assert: register() should complete without error.
        $feature->register();
        self::assertTrue(true);
    }

    public function test_feature_can_be_instantiated_with_dependencies(): void
    {
        $settings        = Mockery::mock(SettingsRepository::class);
        $searchStatistics = Mockery::mock(SearchStatisticsRepository::class);

        $feature = new CliFeature($settings, $searchStatistics);

        self::assertInstanceOf(CliFeature::class, $feature);
    }
}
