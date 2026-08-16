<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Synonyms;

use Mockery;
use TypesenseSearch\Synonyms\Database;
use TypesenseSearch\Synonyms\Repository;
use TypesenseSearch\Tests\TestCase;

class RepositoryTest extends TestCase
{
    public function test_delete_marks_all_remaining_rules_pending_when_rule_was_previously_synced(): void
    {
        global $wpdb;

        $wpdb = Mockery::mock();
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('prepare')->once()->andReturnArg(0);
        $wpdb->shouldReceive('get_row')
            ->once()
            ->andReturn((object) ['synced_at' => '2024-01-01 00:00:00']);
        $wpdb->shouldReceive('delete')
            ->once()
            ->with(Database::tableName(), ['id' => 42], ['%d'])
            ->andReturn(1);
        $wpdb->shouldReceive('query')
            ->once()
            ->with("UPDATE wp_typesense_synonyms SET synced_at = NULL, sync_status = 'pending', sync_error = NULL")
            ->andReturn(1);

        self::assertTrue((new Repository())->delete(42));
    }

    public function test_delete_does_not_mark_rules_pending_when_delete_fails(): void
    {
        global $wpdb;

        $wpdb = Mockery::mock();
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('prepare')->once()->andReturnArg(0);
        $wpdb->shouldReceive('get_row')
            ->once()
            ->andReturn((object) ['synced_at' => '2024-01-01 00:00:00']);
        $wpdb->shouldReceive('delete')
            ->once()
            ->with(Database::tableName(), ['id' => 42], ['%d'])
            ->andReturn(false);
        $wpdb->shouldNotReceive('query');

        self::assertFalse((new Repository())->delete(42));
    }

    public function test_delete_of_never_synced_rule_does_not_mark_remaining_rules_pending(): void
    {
        global $wpdb;

        $wpdb = Mockery::mock();
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('prepare')->once()->andReturnArg(0);
        $wpdb->shouldReceive('get_row')
            ->once()
            ->andReturn((object) ['synced_at' => null]);
        $wpdb->shouldReceive('delete')
            ->once()
            ->with(Database::tableName(), ['id' => 7], ['%d'])
            ->andReturn(1);
        $wpdb->shouldNotReceive('query');

        self::assertTrue((new Repository())->delete(7));
    }
}
