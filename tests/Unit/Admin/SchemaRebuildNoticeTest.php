<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Mockery;
use TypesenseSearch\Admin\SchemaRebuildNotice;
use TypesenseSearch\Admin\Settings\OptionKeys;
use TypesenseSearch\Multisite\NetworkSettingsRepository;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Tests\TestCase;
use TypesenseSearch\Typesense\EmbeddingModels;
use TypesenseSearch\Typesense\SchemaInspector;

class SchemaRebuildNoticeTest extends TestCase
{
    public function test_register_watches_schema_affecting_options(): void
    {
        (new SchemaRebuildNotice())->register();

        foreach ([
            OptionKeys::OPTION_VECTOR_ENABLED,
            OptionKeys::OPTION_VECTOR_MODEL,
            OptionKeys::OPTION_STEMMING_ENABLED,
        ] as $option) {
            self::assertNotFalse(has_action('update_option_' . $option));
        }
    }

    public function test_renders_warning_when_vector_enabled_but_schema_lacks_embedding(): void
    {
        $network = Mockery::mock(NetworkSettingsRepository::class);
        $network->shouldReceive('isNetworkActivated')->andReturn(false);

        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('canUseTypesense')->andReturn(true);
        $settings->shouldReceive('getRemote')->andReturn('https://search.example.com');
        $settings->shouldReceive('getCollectionName')->andReturn('main_col');
        $settings->shouldReceive('isVectorSearchEnabled')->andReturn(true);
        $settings->shouldReceive('getVectorModel')->andReturn(EmbeddingModels::DEFAULT_MODEL);
        $settings->shouldReceive('isStemmingEnabled')->andReturn(false);
        $settings->shouldReceive('network')->andReturn($network);

        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_option')->alias(static function (string $key, mixed $default = false): mixed {
            if ($key === SchemaInspector::OPTION_SCHEMA_STATE) {
                return [
                    'remote'          => 'https://search.example.com',
                    'collection'      => 'main_col',
                    'has_embedding'   => false,
                    'embedding_model' => '',
                    'has_stemming'    => false,
                    'verified_at'     => 123,
                ];
            }
            return $default;
        });
        Functions\when('admin_url')->returnArg(1);
        Functions\when('esc_html__')->returnArg(1);
        Functions\when('esc_html')->returnArg(1);
        Functions\when('esc_url')->returnArg(1);

        ob_start();
        (new SchemaRebuildNotice($settings))->renderNotice();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('notice-warning', $html);
        self::assertStringContainsString('wp typesense rebuild', $html);
    }

    public function test_renders_nothing_when_schema_matches_settings(): void
    {
        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('canUseTypesense')->andReturn(true);
        $settings->shouldReceive('getRemote')->andReturn('https://search.example.com');
        $settings->shouldReceive('getCollectionName')->andReturn('main_col');
        $settings->shouldReceive('isVectorSearchEnabled')->andReturn(true);
        $settings->shouldReceive('getVectorModel')->andReturn(EmbeddingModels::DEFAULT_MODEL);
        $settings->shouldReceive('isStemmingEnabled')->andReturn(false);

        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_option')->justReturn([
            'remote'          => 'https://search.example.com',
            'collection'      => 'main_col',
            'has_embedding'   => true,
            'embedding_model' => EmbeddingModels::DEFAULT_MODEL,
            'has_stemming'    => false,
            'verified_at'     => 123,
        ]);

        ob_start();
        (new SchemaRebuildNotice($settings))->renderNotice();

        self::assertSame('', ob_get_clean());
    }
}
