<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\PinnedResults;

use Mockery;
use TypesenseSearch\PinnedResults\Database;
use TypesenseSearch\PinnedResults\Repository;
use TypesenseSearch\Services\SettingsRepository;
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

        self::assertTrue((new Repository(Mockery::mock(SettingsRepository::class)))->delete(42));
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

        self::assertFalse((new Repository(Mockery::mock(SettingsRepository::class)))->delete(42));
    }

    /**
     * Item 9 — intended behavior after the sync-state fix.
     *
     * When a rule that was never pushed to Typesense (synced_at IS NULL) is
     * deleted, the remaining rules should not be marked pending because
     * Typesense's curation sets were never affected by that rule.
     *
     * The current implementation does not make this distinction: it always calls
     * markAllPending() on any successful delete. This test documents the target
     * behavior and will be un-skipped once item 9 is implemented.
     *
     * Expected implementation change: delete() should fetch the rule first and
     * only call markAllPending() when synced_at is not null.
     */
    public function test_delete_of_never_synced_rule_does_not_mark_remaining_rules_pending(): void
    {
        $this->markTestIncomplete(
            'Item 9: Repository::delete() must check synced_at before calling markAllPending(). ' .
            'Remove this skip and implement the guard in Repository::delete().'
        );

        global $wpdb;

        $wpdb = Mockery::mock();
        $wpdb->prefix = 'wp_';
        // The fixed implementation must read the rule before deleting it.
        $wpdb->shouldReceive('prepare')->andReturnArg(0);
        $wpdb->shouldReceive('get_row')
            ->once()
            ->andReturn((object) ['id' => 7, 'synced_at' => null]);
        $wpdb->shouldReceive('delete')
            ->once()
            ->with(Database::tableName(), ['id' => 7], ['%d'])
            ->andReturn(1);
        // markAllPending() must NOT be called — Typesense was never told about
        // this rule so the other curation sets are still correct.
        $wpdb->shouldNotReceive('query');

        self::assertTrue((new Repository(Mockery::mock(SettingsRepository::class)))->delete(7));
    }
}
