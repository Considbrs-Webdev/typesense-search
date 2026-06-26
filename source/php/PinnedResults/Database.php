<?php

namespace TypesenseSearch\PinnedResults;

/**
 * Owns the pinned-results table and its schema migrations.
 */
class Database
{
    public const OPTION_DB_VERSION = 'typesense_search_pinned_results_db_version';
    public const DB_VERSION = '1';

    public static function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'typesense_pinned_results';
    }

    public static function maybeMigrate(): void
    {
        if ((string) get_option(self::OPTION_DB_VERSION, '') === self::DB_VERSION) {
            return;
        }

        self::migrate();
    }

    public static function migrate(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::tableName();
        $charsetCollate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            synced_at datetime NULL DEFAULT NULL,
            phrase varchar(191) NOT NULL,
            normalized_phrase varchar(191) NOT NULL,
            match_type varchar(20) NOT NULL DEFAULT 'exact',
            items longtext NOT NULL,
            sync_status varchar(20) NOT NULL DEFAULT 'draft',
            sync_error text NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY normalized_phrase (normalized_phrase),
            KEY updated_at (updated_at),
            KEY sync_status (sync_status)
        ) {$charsetCollate};";

        dbDelta($sql);
        update_option(self::OPTION_DB_VERSION, self::DB_VERSION, false);
    }

    public static function drop(): void
    {
        global $wpdb;

        $table = self::tableName();
        $wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        delete_option(self::OPTION_DB_VERSION);
    }
}
