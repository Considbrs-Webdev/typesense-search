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
    public function test_hides_mode_switcher_when_vector_search_is_not_ready(): void
    {
        Functions\when('is_search')->justReturn(true);
        Functions\when('home_url')->justReturn('https://example.se/');
        Functions\when('apply_filters')->returnArg(2);

        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('canUseTypesense')->andReturn(true);
        $settings->shouldReceive('getSortDisplay')->andReturn('radio');
        $settings->shouldReceive('isVectorSearchReady')->andReturn(false);
        $settings->shouldReceive('isVectorModeSwitcherEnabled')->andReturn(true);

        $data = (new EnrichSearchTemplate($settings))->addTypesenseViewData([]);

        self::assertFalse($data['showSearchModeSwitcher']);
        self::assertSame('keyword', $data['defaultSearchMode']);
    }

    public function test_exposes_mode_switcher_when_vector_search_is_ready_and_switcher_enabled(): void
    {
        Functions\when('is_search')->justReturn(true);
        Functions\when('home_url')->justReturn('https://example.se/');
        Functions\when('apply_filters')->returnArg(2);

        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('canUseTypesense')->andReturn(true);
        $settings->shouldReceive('getSortDisplay')->andReturn('dropdown');
        $settings->shouldReceive('isVectorSearchReady')->andReturn(true);
        $settings->shouldReceive('isVectorModeSwitcherEnabled')->andReturn(true);
        $settings->shouldReceive('getVectorDefaultMode')->andReturn('hybrid');

        $data = (new EnrichSearchTemplate($settings))->addTypesenseViewData([]);

        self::assertTrue($data['showSearchModeSwitcher']);
        self::assertSame('hybrid', $data['defaultSearchMode']);
        self::assertSame('Search mode', $data['lang']->searchModeBy);
    }
}
