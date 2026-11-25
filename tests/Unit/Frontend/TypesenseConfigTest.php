<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Frontend;

use Brain\Monkey\Functions;
use Mockery;
use TypesenseSearch\Frontend\TypesenseConfig;
use TypesenseSearch\Multisite\NetworkSettingsRepository;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Tests\TestCase;

class TypesenseConfigTest extends TestCase
{
    private function baseSettingsMock(NetworkSettingsRepository $network): Mockery\MockInterface
    {
        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('canUseTypesense')->andReturn(true);
        $settings->shouldReceive('isQuickSearchEnabled')->andReturn(false);
        $settings->shouldReceive('getRemote')->andReturn('https://search.example.se');
        $settings->shouldReceive('getFrontendHost')->andReturn('');
        $settings->shouldReceive('getCollectionName')->andReturn('col_main');
        $settings->shouldReceive('getSearchKey')->andReturn('scoped_key_main');
        $settings->shouldReceive('getHitsPerPage')->andReturn(20);
        $settings->shouldReceive('isDebounceEnabled')->andReturn(true);
        $settings->shouldReceive('getDebounceDelay')->andReturn(300);
        $settings->shouldReceive('getHighlightAffixNumTokens')->andReturn(15);
        $settings->shouldReceive('getQueryByWeights')->andReturn('10,3,1,8,2');
        $settings->shouldReceive('getTruncator')->andReturn('[...]');
        $settings->shouldReceive('getSortDisplay')->andReturn('radio');
        $settings->shouldReceive('isSearchLoggingEnabled')->andReturn(false);
        $settings->shouldReceive('getFacets')->andReturn([]);
        $settings->shouldReceive('network')->andReturn($network);

        return $settings;
    }

    private function stubFrontendWordPressFunctions(?string &$inlineJs): void
    {
        Functions\when('is_search')->justReturn(true);
        Functions\when('get_locale')->justReturn('sv_SE');
        Functions\when('esc_url_raw')->returnArg(1);
        Functions\when('get_bloginfo')->alias(static fn (string $show = ''): string => $show === 'charset' ? 'UTF-8' : 'Main Site');
        Functions\when('wp_get_document_title')->justReturn('Search — Main Site');
        Functions\when('apply_filters')->returnArg(2);
        Functions\when('wp_add_inline_script')->alias(
            static function (string $handle, string $data) use (&$inlineJs): bool {
                if ($handle === 'typesense-search') {
                    $inlineJs = $data;
                }
                return true;
            }
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeInlineConfig(?string $inlineJs): array
    {
        self::assertNotNull($inlineJs);
        $prefix = 'var typesenseConfig = ';
        self::assertStringStartsWith($prefix, $inlineJs);
        $json = rtrim(substr($inlineJs, strlen($prefix)), ';');
        $decoded = json_decode($json, true);
        self::assertIsArray($decoded);
        return $decoded;
    }

    public function test_single_site_falls_back_to_keyword_when_vector_not_ready_and_omits_multisearch(): void
    {
        $inlineJs = null;
        $this->stubFrontendWordPressFunctions($inlineJs);

        $network = Mockery::mock(NetworkSettingsRepository::class);
        $network->shouldReceive('isNetworkActivated')->andReturn(false);

        $settings = $this->baseSettingsMock($network);
        $settings->shouldReceive('getVectorDefaultMode')->andReturn('hybrid');
        $settings->shouldReceive('isVectorSearchReady')->andReturn(false);
        $settings->shouldReceive('isVectorModeSwitcherEnabled')->andReturn(true);

        (new TypesenseConfig($settings))->localizeConfig();
        $decoded = $this->decodeInlineConfig($inlineJs);

        self::assertSame([
            'enabled'          => false,
            'defaultMode'      => 'keyword',
            'showModeSwitcher' => false,
        ], $decoded['vectorSearch']);
        self::assertArrayNotHasKey('multiSearch', $decoded);
    }

    public function test_single_site_localizes_vector_config_when_schema_is_ready(): void
    {
        $inlineJs = null;
        $this->stubFrontendWordPressFunctions($inlineJs);

        $network = Mockery::mock(NetworkSettingsRepository::class);
        $network->shouldReceive('isNetworkActivated')->andReturn(false);

        $settings = $this->baseSettingsMock($network);
        $settings->shouldReceive('getVectorDefaultMode')->andReturn('semantic');
        $settings->shouldReceive('isVectorSearchReady')->andReturn(true);
        $settings->shouldReceive('isVectorModeSwitcherEnabled')->andReturn(true);

        (new TypesenseConfig($settings))->localizeConfig();
        $decoded = $this->decodeInlineConfig($inlineJs);

        self::assertSame([
            'enabled'          => true,
            'defaultMode'      => 'semantic',
            'showModeSwitcher' => true,
        ], $decoded['vectorSearch']);
        self::assertArrayNotHasKey('multiSearch', $decoded);
    }

    public function test_network_mode_localizes_combined_vector_and_multi_search_config(): void
    {
        $inlineJs = null;
        $this->stubFrontendWordPressFunctions($inlineJs);

        $network = Mockery::mock(NetworkSettingsRepository::class);
        $network->shouldReceive('isNetworkActivated')->andReturn(true);

        $settings = $this->baseSettingsMock($network);
        $settings->shouldReceive('getVectorDefaultMode')->andReturn('hybrid');
        $settings->shouldReceive('isVectorSearchReady')->andReturn(true);
        $settings->shouldReceive('isVectorModeSwitcherEnabled')->andReturn(false);
        $settings->shouldReceive('getMultiSearchResolution')->andReturn([
            'configured' => true,
            'enabled'    => true,
            'collections'=> [
                [
                    'blog_id'          => 1,
                    'site_name'        => 'Main Site',
                    'home_url'         => 'https://main.example.se/',
                    'collection'       => 'col_main',
                    'search_key'       => 'scoped_key_main',
                    'enable_curations' => true,
                ],
                [
                    'blog_id'          => 2,
                    'site_name'        => 'Culture Portal',
                    'home_url'         => 'https://culture.example.se/',
                    'collection'       => 'col_culture',
                    'search_key'       => 'scoped_key_culture',
                    'enable_curations' => false,
                ],
            ],
        ]);

        (new TypesenseConfig($settings))->localizeConfig();
        $decoded = $this->decodeInlineConfig($inlineJs);

        self::assertSame([
            'enabled'          => true,
            'defaultMode'      => 'hybrid',
            'showModeSwitcher' => false,
        ], $decoded['vectorSearch']);

        self::assertTrue($decoded['multiSearch']['enabled']);
        self::assertCount(2, $decoded['multiSearch']['collections']);
        self::assertTrue($decoded['multiSearch']['collections'][0]['enableCurations']);
        self::assertFalse($decoded['multiSearch']['collections'][1]['enableCurations']);
        self::assertSame('scoped_key_culture', $decoded['multiSearch']['collections'][1]['searchKey']);
    }
}
