<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Integration;

/**
 * Smoke test: real WordPress save hooks, real Typesense upsert, DocumentBuilder parity.
 */
final class TrivialTypesenseConnectionTest extends IntegrationTestCase
{
    public function test_published_page_is_indexed_and_matches_document_builder(): void
    {
        $postId = wp_insert_post([
            'post_title'   => 'Integration smoke page',
            'post_name'    => 'integration-smoke-page',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => 'Hello Typesense integration tests.',
        ], true);

        self::assertIsInt($postId);
        self::assertGreaterThan(0, $postId);

        $this->waitForIndex([(int) $postId]);
        $this->assertIndexMatchesDatabase((int) $postId);
    }
}
