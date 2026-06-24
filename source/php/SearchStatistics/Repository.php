<?php

namespace TypesenseSearch\SearchStatistics;

use TypesenseSearch\Services\SettingsRepository;

/**
 * Reads and writes the small, privacy-minimised search statistics dataset.
 */
class Repository
{
    /**
     * Insert one unique query per anonymous browser session, updating its
     * last-seen metadata if that session repeats the query later.
     */
    public function record(string $query, int $found, string $surface, string $sessionId): bool
    {
        global $wpdb;

        $query = $this->cleanQuery($query);
        $normalized = $this->normalizeQuery($query);
        if ($query === '' || $normalized === '' || !$this->isValidSurface($surface)) {
            return false;
        }

        $sessionHash = hash_hmac('sha256', $sessionId, wp_salt('auth'));
        $normalizedHash = hash('sha256', $normalized);

        $now = current_time('mysql', true);
        $found = max(0, $found);
        $table = Database::tableName();

        return false !== $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (created_at, last_searched_at, query_text, normalized_query, normalized_hash, found, surface, last_found, last_surface, session_hash)
                VALUES (%s, %s, %s, %s, %s, %d, %s, %d, %s, %s)
                ON DUPLICATE KEY UPDATE
                    last_searched_at = VALUES(last_searched_at),
                    query_text = VALUES(query_text),
                    last_found = VALUES(last_found),
                    last_surface = VALUES(last_surface)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $now,
                $now,
                $query,
                $normalized,
                $normalizedHash,
                $found,
                $surface,
                $found,
                $surface,
                $sessionHash
            )
        );
    }

    /**
     * @return array{latest: array<int, array<string, mixed>>, failed: array<int, array<string, mixed>>, popular: array<int, array<string, mixed>>, total: int}
     */
    public function getWidgetData(int $limit = 8): array
    {
        global $wpdb;

        $table = Database::tableName();
        $limit = max(1, min(50, $limit));

        return [
            'latest' => $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT query_text, last_found AS found, last_surface AS surface, last_searched_at AS searched_at FROM {$table} ORDER BY last_searched_at DESC, id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $limit
                ),
                ARRAY_A
            ) ?: [],
            'failed' => $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT MAX(query_text) AS query_text, COUNT(*) AS count, MAX(last_searched_at) AS last_searched
                    FROM {$table} WHERE last_found = 0 GROUP BY normalized_query
                    ORDER BY count DESC, last_searched DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $limit
                ),
                ARRAY_A
            ) ?: [],
            'popular' => $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT MAX(query_text) AS query_text, COUNT(*) AS count, MAX(last_searched_at) AS last_searched
                    FROM {$table} GROUP BY normalized_query
                    ORDER BY count DESC, last_searched DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $limit
                ),
                ARRAY_A
            ) ?: [],
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}"), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        ];
    }

    /**
     * Delete expired data in bounded batches so the scheduled task does not
     * hold a large table lock on high-traffic sites.
     */
    public function prune(int $retentionDays, int $batchSize = 1000): int
    {
        global $wpdb;

        if ($retentionDays < 1) {
            return 0;
        }

        $cutoff = gmdate('Y-m-d H:i:s', time() - ($retentionDays * DAY_IN_SECONDS));
        $table = Database::tableName();
        $deleted = 0;

        do {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} WHERE last_searched_at < %s LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $cutoff,
                    max(1, $batchSize)
                )
            );
            $deleted += max(0, (int) $result);
        } while ($result === $batchSize);

        return $deleted;
    }

    public function clear(): int
    {
        global $wpdb;

        return max(0, (int) $wpdb->query('DELETE FROM ' . Database::tableName())); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /** @param array<int, int> $ids */
    public function deleteSearchLogEntries(array $ids): int
    {
        global $wpdb;

        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        return max(0, (int) $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM ' . Database::tableName() . " WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                ...$ids
            )
        ));
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function getSearchLog(string $mode, string $status, string $context, string $search, string $orderby, string $order, int $perPage, int $page): array
    {
        global $wpdb;

        $mode = $mode === 'grouped' ? 'grouped' : 'events';
        $status = in_array($status, ['hits', 'no-hits'], true) ? $status : 'all';
        $context = in_array($context, ['regular', 'quick'], true) ? $context : 'all';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $perPage = max(1, min(100, $perPage));
        $offset = max(0, ($page - 1) * $perPage);
        $table = Database::tableName();
        [$whereSql, $whereArgs] = $this->getLogWhere($status, $context, $search, 'e');

        $totalSearches = "(SELECT normalized_query, COUNT(*) AS total_searches FROM {$table} GROUP BY normalized_query) totals";

        if ($mode === 'grouped') {
            $groupOrderBy = match ($orderby) {
                'term' => 'grouped.query_text',
                'count' => 'totals.total_searches',
                default => 'grouped.last_searched_at',
            };
            $grouped = "(SELECT e.normalized_query, MAX(e.query_text) AS query_text, MAX(e.last_searched_at) AS last_searched_at,
                GROUP_CONCAT(DISTINCT e.last_surface ORDER BY e.last_surface SEPARATOR ',') AS contexts
                FROM {$table} e {$whereSql} GROUP BY e.normalized_query) grouped";
            $sql = "SELECT grouped.query_text, grouped.last_searched_at, grouped.contexts, totals.total_searches
                FROM {$grouped} INNER JOIN {$totalSearches} ON totals.normalized_query = grouped.normalized_query
                ORDER BY {$groupOrderBy} {$order} LIMIT %d OFFSET %d";
            $items = $wpdb->get_results(
                $wpdb->prepare($sql, ...array_merge($whereArgs, [$perPage, $offset])), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                ARRAY_A
            ) ?: [];
            $total = (int) $this->prepareAndGetVar(
                "SELECT COUNT(DISTINCT e.normalized_query) FROM {$table} e {$whereSql}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $whereArgs
            );
        } else {
            $eventOrderBy = match ($orderby) {
                'term' => 'e.query_text',
                'hits' => 'e.last_found',
                'count' => 'totals.total_searches',
                default => 'e.last_searched_at',
            };
            $sql = "SELECT e.id, e.query_text, e.last_searched_at, e.last_surface, e.last_found, totals.total_searches
                FROM {$table} e INNER JOIN {$totalSearches} ON totals.normalized_query = e.normalized_query
                {$whereSql} ORDER BY {$eventOrderBy} {$order} LIMIT %d OFFSET %d";
            $items = $wpdb->get_results(
                $wpdb->prepare($sql, ...array_merge($whereArgs, [$perPage, $offset])), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                ARRAY_A
            ) ?: [];
            $total = (int) $this->prepareAndGetVar(
                "SELECT COUNT(*) FROM {$table} e {$whereSql}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $whereArgs
            );
        }

        return compact('items', 'total');
    }

    public function canRecord(string $query, SettingsRepository $settings): bool
    {
        return $settings->isSearchLoggingEnabled()
            && $this->characterLength($this->normalizeQuery($this->cleanQuery($query))) >= $settings->getSearchLoggingMinimumCharacters();
    }

    public function cleanQuery(string $query): string
    {
        $query = trim((string) preg_replace('/\s+/u', ' ', sanitize_text_field($query)));

        return function_exists('mb_substr')
            ? mb_substr($query, 0, 191, 'UTF-8')
            : substr($query, 0, 191);
    }

    public function normalizeQuery(string $query): string
    {
        $query = trim((string) preg_replace('/\s+/u', ' ', $query));

        if (class_exists('Normalizer')) {
            $query = \Normalizer::normalize($query, \Normalizer::FORM_C) ?: $query;
        }

        return function_exists('mb_strtolower')
            ? mb_strtolower($query, 'UTF-8')
            : strtolower($query);
    }

    private function characterLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private function isValidSurface(string $surface): bool
    {
        return in_array($surface, ['quick', 'regular'], true);
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    private function getLogWhere(string $status, string $context, string $search, string $alias): array
    {
        global $wpdb;

        $clauses = [];
        $args = [];
        if ($status === 'hits') {
            $clauses[] = "{$alias}.last_found > 0";
        } elseif ($status === 'no-hits') {
            $clauses[] = "{$alias}.last_found = 0";
        }
        if ($context !== 'all') {
            $clauses[] = "{$alias}.last_surface = %s";
            $args[] = $context;
        }
        $search = $this->normalizeQuery($this->cleanQuery($search));
        if ($search !== '') {
            $clauses[] = "{$alias}.normalized_query LIKE %s";
            $args[] = '%' . $wpdb->esc_like($search) . '%';
        }

        return [empty($clauses) ? '' : 'WHERE ' . implode(' AND ', $clauses), $args];
    }

    /** @param array<int, string> $args */
    private function prepareAndGetVar(string $sql, array $args): mixed
    {
        global $wpdb;

        return $wpdb->get_var(empty($args)
            ? $sql
            : $wpdb->prepare($sql, ...$args) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
    }
}
