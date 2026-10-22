<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Integration;

use Typesense\Client;
use TypesenseSearch\Admin\Settings;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Services\TypesenseClientService;
use TypesenseSearch\Tests\Integration\Support\IndexDiff;
use TypesenseSearch\Tests\Integration\Support\IndexWaiter;
use TypesenseSearch\Typesense\ClientFactory;
use TypesenseSearch\Typesense\Collection;
use WP_UnitTestCase;

abstract class IntegrationTestCase extends WP_UnitTestCase
{
    protected Client $typesenseClient;

    protected string $collectionName;

    private IndexWaiter $indexWaiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collectionName = $this->makeCollectionName();
        $this->configurePluginConnection($this->collectionName);

        $this->typesenseClient = ClientFactory::build(
            $this->typesenseHost(),
            $this->typesenseApiKey(),
            10
        );

        if (Collection::exists($this->typesenseClient, $this->collectionName)) {
            Collection::drop($this->typesenseClient, $this->collectionName);
        }
        Collection::create($this->typesenseClient, $this->collectionName, new SettingsRepository());

        $this->indexWaiter = new IndexWaiter($this->typesenseClient, $this->collectionName);

        // Reset Typesense client cache inside the plugin between tests.
        $this->resetPluginClientCache();
    }

    protected function tearDown(): void
    {
        if (isset($this->typesenseClient, $this->collectionName)) {
            try {
                Collection::drop($this->typesenseClient, $this->collectionName);
            } catch (\Throwable) {
                // Best-effort cleanup.
            }
        }

        parent::tearDown();
    }

    protected function typesenseHost(): string
    {
        return getenv('TYPESENSE_HOST') ?: 'http://127.0.0.1:8118';
    }

    protected function typesenseApiKey(): string
    {
        return getenv('TYPESENSE_API_KEY') ?: 'typesense-integration-test-key';
    }

    protected function makeCollectionName(): string
    {
        $class = strtolower(str_replace('\\', '_', static::class));
        $method = $this->getName();
        $token = substr(md5($class . '::' . $method . microtime(true)), 0, 10);

        return 'ts_it_' . preg_replace('/[^a-z0-9_]/', '_', $class) . '_' . $token;
    }

    protected function configurePluginConnection(string $collectionName): void
    {
        update_option(Settings::OPTION_REMOTE, $this->typesenseHost());
        update_option(Settings::OPTION_ADMIN_KEY, $this->typesenseApiKey());
        update_option(Settings::OPTION_INDEX_NAME, $collectionName);
        update_option(Settings::OPTION_POST_TYPES, ['page']);
        update_option(Settings::OPTION_INDEX_PDF, 0);
        update_option(Settings::OPTION_INDEX_MODULARITY, 0);

        // Pretty hierarchical page permalinks (/%pagename%/ includes parent slugs).
        update_option('permalink_structure', '/%pagename%/');
        flush_rewrite_rules(true);

        $this->resetPluginClientCache();
    }

    protected function resetPluginClientCache(): void
    {
        // TypesenseClientService caches by config hash; new SettingsRepository each time is fine in tests.
        $ref = new \ReflectionClass(TypesenseClientService::class);
        // No global singleton — each App bootstraps its own. wp_cache_flush clears WP object cache only.
        wp_cache_flush();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getIndexedDocument(int $postId): ?array
    {
        return $this->indexWaiter->fetchDocument((string) $postId);
    }

    /**
     * @param int[] $postIds
     */
    protected function waitForIndex(array $postIds): void
    {
        $this->indexWaiter->waitUntilPostsMatch($postIds);
    }

    protected function assertIndexMatchesDatabase(int $postId, string $message = ''): void
    {
        $post = get_post($postId);
        self::assertInstanceOf(\WP_Post::class, $post, 'Post ' . $postId . ' must exist');

        $indexed = $this->getIndexedDocument($postId);
        $diff = IndexDiff::diffPost($indexed, $post);

        $prefix = $message !== '' ? $message . "\n" : '';
        self::assertSame([], $diff, $prefix . "Index out of sync for post {$postId}:\n" . implode("\n", $diff));
    }

    /**
     * @param int[] $postIds
     */
    protected function assertPostsMatchDatabase(array $postIds): void
    {
        foreach ($postIds as $postId) {
            $this->assertIndexMatchesDatabase((int) $postId);
        }
    }

    /**
     * Re-save posts so wp_after_insert_post indexes them (initial tree setup).
     *
     * @param int[] $postIds
     */
    protected function indexPosts(array $postIds): void
    {
        foreach ($postIds as $postId) {
            wp_update_post(['ID' => (int) $postId]);
        }
        $this->waitForIndex($postIds);
    }

    /**
     * Simulate Nested Pages writing post_parent via raw SQL (bypasses WP hooks).
     */
    protected function rawSqlSetPostParent(int $postId, int $newParentId): void
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->update(
            $wpdb->posts,
            ['post_parent' => $newParentId],
            ['ID' => $postId],
            ['%d'],
            ['%d']
        );
        clean_post_cache($postId);
    }
}
