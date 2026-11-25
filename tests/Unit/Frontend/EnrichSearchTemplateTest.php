<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Frontend;

use Brain\Monkey\Functions;
use Mockery;
use TypesenseSearch\Frontend\EnrichSearchTemplate;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Tests\TestCase;

class EnrichSearchTemplateTest extends TestCase
{
    public function test_falls_back_to_keyword_strategy_when_vector_search_is_not_ready(): void
    {
        Functions\when('is_search')->justReturn(true);
        Functions\when('home_url')->justReturn('https://example.se/');
        Functions\when('apply_filters')->returnArg(2);

        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('canUseTypesense')->andReturn(true);
        $settings->shouldReceive('getSortDisplay')->andReturn('radio');
        $settings->shouldReceive('isVectorSearchReady')->andReturn(false);

        $data = (new EnrichSearchTemplate($settings))->addTypesenseViewData([]);

        self::assertArrayNotHasKey('showSearchModeSwitcher', $data);
        self::assertSame('keyword', $data['vectorSearchStrategy']);
    }

    public function test_exposes_active_strategy_and_spotlight_rescue_labels_when_vector_search_is_ready(): void
    {
        Functions\when('is_search')->justReturn(true);
        Functions\when('home_url')->justReturn('https://example.se/');
        Functions\when('apply_filters')->returnArg(2);

        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('canUseTypesense')->andReturn(true);
        $settings->shouldReceive('getSortDisplay')->andReturn('dropdown');
        $settings->shouldReceive('isVectorSearchReady')->andReturn(true);
        $settings->shouldReceive('getVectorStrategy')->andReturn('parallel');

        $data = (new EnrichSearchTemplate($settings))->addTypesenseViewData([]);

        self::assertSame('parallel', $data['vectorSearchStrategy']);
        self::assertSame('Suggested shortcuts', $data['lang']->spotlightHeading);
        self::assertSame('Related pages', $data['lang']->rescueHeading);
    }
}
