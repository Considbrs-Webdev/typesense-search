<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\PinnedResults;

use Mockery;
use TypesenseSearch\PinnedResults\Database;
use TypesenseSearch\PinnedResults\Repository;
use TypesenseSearch\Tests\TestCase;

class RepositoryTest extends TestCase
{
    public function test_delete_marks_all_remaining_rules_pending_after_successful_delete(): void
    {
        global $wpdb;

        $wpdb = Mockery::mock();
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('delete')
            ->once()
            ->with(Database::tableName(), ['id' => 42], ['%d'])
            ->andReturn(1);
        $wpdb->shouldReceive('query')
            ->once()
            ->with("UPDATE wp_typesense_pinned_results SET synced_at = NULL, sync_status = 'pending', sync_error = NULL")
            ->andReturn(1);

        self::assertTrue((new Repository())->delete(42));
    }

    public function test_delete_does_not_mark_rules_pending_when_delete_fails(): void
    {
        global $wpdb;

        $wpdb = Mockery::mock();
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('delete')
            ->once()
            ->with(Database::tableName(), ['id' => 42], ['%d'])
            ->andReturn(false);
        $wpdb->shouldNotReceive('query');

        self::assertFalse((new Repository())->delete(42));
    }
}
