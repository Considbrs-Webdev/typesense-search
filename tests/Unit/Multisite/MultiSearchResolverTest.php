<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Multisite;

use Brain\Monkey\Functions;
use TypesenseSearch\Admin\Settings\OptionKeys;
use TypesenseSearch\Multisite\MultiSearchResolver;
use TypesenseSearch\Multisite\NetworkSettingsRepository;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Tests\TestCase;
use TypesenseSearch\Typesense\EmbeddingModels;
use TypesenseSearch\Typesense\SchemaInspector;

class MultiSearchResolverTest extends TestCase
{
    private int $currentSite = 1;
    private array $blogOptions = [];
    private array $networkOptions = [];

    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('is_multisite')->justReturn(true);
        Functions\when('get_current_blog_id')->alias(fn () => $this->currentSite);
        Functions\when('get_current_network_id')->justReturn(1);
        Functions\when('wp_get_environment_type')->justReturn('production');
        Functions\when('get_site')->alias(function (int $id) {
            if ($id === 99) {
                return null;
            }
            return (object) [
                'blog_id'    => $id,
                'network_id' => 1,
                'archived'   => 0,
                'spam'       => 0,
                'deleted'    => 0,
            ];
        });
        Functions\when('get_home_url')->alias(static fn (?int $id = null): string => match ($id) {
            1, null => 'https://main.example.se/',
            2       => 'https://culture.example.se/',
            3       => 'https://tourism.example.se/',
            default => "https://site{$id}.example.se/",
        });

        $this->networkOptions = [
            'active_sitewide_plugins'            => ['typesense-search/typesense-search.php' => 1],
            NetworkSettingsRepository::REMOTE    => 'https://search.example.se',
            NetworkSettingsRepository::ADMIN_KEY => 'admin-secret',
            NetworkSettingsRepository::ENABLED   => [1, 2, 3],
            NetworkSettingsRepository::VECTOR_MODEL => EmbeddingModels::DEFAULT_MODEL,
        ];

        Functions\when('get_network_option')->alias(
            fn ($networkId, string $key, mixed $default = false) => $this->networkOptions[$key] ?? $default
        );
        Functions\when('get_blog_option')->alias(
            fn (int $blogId, string $key, mixed $default = false) => $this->blogOptions[$blogId][$key] ?? $default
        );
        Functions\when('get_option')->alias(
            fn (string $key, mixed $default = false) => $this->blogOptions[$this->currentSite][$key] ?? $default
        );

        foreach ([1 => 'Main Municipality', 2 => 'Culture Portal', 3 => 'Tourism Portal'] as $id => $name) {
            $this->blogOptions[$id]['blogname'] = $name;
            $this->markSiteReady($id, "col_b{$id}", "search_key_b{$id}");
        }
    }

    private function markSiteReady(int $blogId, string $collection, string $searchKey): void
    {
        $this->blogOptions[$blogId][NetworkSettingsRepository::STATE] = [
            'active' => [
                'identity' => [
                    'home'        => rtrim(match ($blogId) {
                        1 => 'https://main.example.se/',
                        2 => 'https://culture.example.se/',
                        3 => 'https://tourism.example.se/',
                        default => "https://site{$blogId}.example.se/",
                    }, '/'),
                    'environment' => 'production',
                    'remote'      => 'https://search.example.se',
                    'network'     => 1,
                    'site'        => $blogId,
                    'prefix'      => '',
                ],
                'collection' => $collection,
                'key'        => $searchKey,
            ],
        ];
    }

    private function markVectorReady(int $blogId, string $collection, string $model = EmbeddingModels::DEFAULT_MODEL): void
    {
        $this->blogOptions[$blogId][OptionKeys::OPTION_VECTOR_ENABLED] = 1;
        $this->blogOptions[$blogId][SchemaInspector::OPTION_SCHEMA_STATE] = [
            'remote'          => 'https://search.example.se',
            'collection'      => $collection,
            'has_embedding'   => true,
            'embedding_model' => $model,
            'has_stemming'    => false,
            'verified_at'     => 1000,
        ];
    }

    public function test_sanitize_map_strips_disabled_sites_duplicates_and_self_references(): void
    {
        $clean = MultiSearchResolver::sanitizeMap([
            '1'  => ['1', '2', '2', '4', 'bad'],
            '2'  => ['3'],
            '4'  => ['1'], // site 4 is not in enabled list
            'bad'=> ['2'],
        ], [1, 2, 3]);

        self::assertSame([
            1 => [2],
            2 => [3],
        ], $clean);
    }

    public function test_returns_empty_when_not_network_activated(): void
    {
        $this->networkOptions['active_sitewide_plugins'] = [];
        $this->networkOptions[NetworkSettingsRepository::MULTISEARCH] = [1 => [2, 3]];

        $resolution = (new MultiSearchResolver())->resolveForCurrentSite();

        self::assertFalse($resolution['configured']);
        self::assertFalse($resolution['enabled']);
        self::assertSame([], $resolution['collections']);
    }

    public function test_resolves_source_first_with_curations_and_siblings_without_curations(): void
    {
        $this->networkOptions[NetworkSettingsRepository::MULTISEARCH] = [1 => [2, 3]];

        $resolution = (new MultiSearchResolver())->resolveForCurrentSite();

        self::assertTrue($resolution['configured']);
        self::assertTrue($resolution['enabled']);
        self::assertCount(3, $resolution['collections']);

        self::assertSame(1, $resolution['collections'][0]['blog_id']);
        self::assertSame('col_b1', $resolution['collections'][0]['collection']);
        self::assertTrue($resolution['collections'][0]['enable_curations']);

        self::assertSame(2, $resolution['collections'][1]['blog_id']);
        self::assertSame('Culture Portal', $resolution['collections'][1]['site_name']);
        self::assertFalse($resolution['collections'][1]['enable_curations']);

        self::assertSame(3, $resolution['collections'][2]['blog_id']);
        self::assertFalse($resolution['collections'][2]['enable_curations']);
    }

    public function test_skips_disabled_or_unready_targets_without_breaking_search(): void
    {
        $this->networkOptions[NetworkSettingsRepository::MULTISEARCH] = [1 => [2, 3]];
        // Disable site 3 in Network Admin without removing it from the saved multisearch map.
        $this->networkOptions[NetworkSettingsRepository::ENABLED] = [1, 2];

        $resolution = (new MultiSearchResolver())->resolveForCurrentSite();

        self::assertTrue($resolution['enabled']);
        self::assertCount(2, $resolution['collections']);
        self::assertCount(1, $resolution['skipped_targets']);
        self::assertSame(3, $resolution['skipped_targets'][0]['blog_id']);

        // If site 2 also becomes unready, multi-search gracefully disables (falls back to single-site search).
        unset($this->blogOptions[2][NetworkSettingsRepository::STATE]);
        $fallback = (new MultiSearchResolver())->resolveForCurrentSite();

        self::assertTrue($fallback['configured']);
        self::assertFalse($fallback['enabled']);
        self::assertCount(2, $fallback['skipped_targets']);
    }

    public function test_vector_and_multisearch_coexist_when_all_collections_have_matching_embedding(): void
    {
        $this->networkOptions[NetworkSettingsRepository::MULTISEARCH] = [1 => [2]];
        $this->markVectorReady(1, 'col_b1');
        $this->markVectorReady(2, 'col_b2');

        $settings   = new SettingsRepository();
        $resolution = $settings->getMultiSearchResolution();

        self::assertTrue($resolution['enabled']);
        self::assertTrue($resolution['vector_compatible']);
        self::assertSame('', $resolution['vector_paused_reason']);
        self::assertTrue($settings->isVectorSearchReady());
    }

    public function test_pauses_vector_and_falls_back_to_keyword_when_a_sibling_lacks_embeddings(): void
    {
        $this->networkOptions[NetworkSettingsRepository::MULTISEARCH] = [1 => [2, 3]];
        $this->markVectorReady(1, 'col_b1');
        $this->markVectorReady(2, 'col_b2');
        // Site 3 has NOT enabled embedded search yet.

        $settings   = new SettingsRepository();
        $resolution = $settings->getMultiSearchResolution();

        self::assertTrue($resolution['enabled']);
        self::assertFalse($resolution['vector_compatible']);
        self::assertStringContainsString('Tourism Portal', $resolution['vector_paused_reason']);
        self::assertTrue($settings->isSiteVectorSearchReady());
        self::assertFalse($settings->isVectorSearchReady());
    }
}
