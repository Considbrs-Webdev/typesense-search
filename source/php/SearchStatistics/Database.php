<?php

namespace TypesenseSearch\SearchStatistics;

/**
 * Owns the search-statistics table and its schema migrations.
 *
 * Search statistics are deliberately kept in WordPress instead of Typesense:
 * they include a recent-event view and can be retained independently from the
 * search index.
 */
class Database
{
    public const OPTION_DB_VERSION = 'typesense_search_statistics_db_version';
    public const DB_VERSION = '3';

    public static function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'typesense_search_events';
    }

    /**
     * Create or migrate the table when the stored schema version is outdated.
     */
    public static function maybeMigrate(): void
    {
        if ((string) get_option(self::OPTION_DB_VERSION, '') === self::DB_VERSION) {
            return;
        }

        self::migrate();
    }

    /**
     * Runs the current schema migration. Future incompatible changes should be
     * added as explicit versioned migrations before updating DB_VERSION.
     */
    public static function migrate(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::tableName();
        $installedVersion = (string) get_option(self::OPTION_DB_VERSION, '');
        $charsetCollate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL,
            last_searched_at datetime NOT NULL,
            query_text varchar(191) NOT NULL,
            normalized_query varchar(191) NOT NULL,
            normalized_hash char(64) NOT NULL,
            found int(10) unsigned NOT NULL DEFAULT 0,
            surface varchar(20) NOT NULL,
            last_found int(10) unsigned NOT NULL DEFAULT 0,
            last_surface varchar(20) NOT NULL,
            session_hash char(64) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY session_query (session_hash, normalized_hash),
            KEY created_at (created_at),
            KEY last_searched_at (last_searched_at),
            KEY last_found_searched_at (last_found, last_searched_at),
            KEY normalized_query (normalized_query)
        ) {$charsetCollate};";

        dbDelta($sql);

        // Version 2 replaces an utf8mb4 composite key with fixed-size hashes.
        // The old key could exceed index limits on older MySQL installations.
        if ($installedVersion === '' || version_compare($installedVersion, '2', '<')) {
            $columnExists = $wpdb->get_var(
                $wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'normalized_hash') // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            );

            if (!$columnExists) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN normalized_hash char(64) NOT NULL AFTER normalized_query"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }

            $wpdb->query("UPDATE {$table} SET normalized_hash = SHA2(normalized_query, 256) WHERE normalized_hash = ''"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("ALTER TABLE {$table} DROP INDEX session_query, ADD UNIQUE KEY session_query (session_hash, normalized_hash)"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        // Version 3 retains the original timestamp for auditing but tracks the
        // most recent repeat within the same session without increasing its
        // unique-session count.
        if ($installedVersion === '' || version_compare($installedVersion, '3', '<')) {
            self::addColumnIfMissing($table, 'last_searched_at', 'datetime NOT NULL AFTER created_at');
            self::addColumnIfMissing($table, 'last_found', 'int(10) unsigned NOT NULL DEFAULT 0 AFTER surface');
            self::addColumnIfMissing($table, 'last_surface', 'varchar(20) NOT NULL AFTER last_found');
            $wpdb->query("UPDATE {$table} SET last_searched_at = created_at, last_found = found, last_surface = surface WHERE last_searched_at = '0000-00-00 00:00:00' OR last_surface = ''"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        // Only mark the migration as complete after dbDelta has run.
        update_option(self::OPTION_DB_VERSION, self::DB_VERSION, false);
    }

    public static function drop(): void
    {
        global $wpdb;

        $table = self::tableName();
        $wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        delete_option(self::OPTION_DB_VERSION);
    }

    private static function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        global $wpdb;

        $exists = $wpdb->get_var(
            $wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        if (!$exists) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
    }
}
