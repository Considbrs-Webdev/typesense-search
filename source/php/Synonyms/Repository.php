<?php

namespace TypesenseSearch\Synonyms;

/**
 * Reads and writes multi-way synonym rules stored in WordPress.
 *
 * Each rule is a group of interchangeable terms: searching for any one of
 * them also matches documents containing any of the others in the group.
 */
class Repository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            'SELECT * FROM ' . Database::tableName() . ' ORDER BY id ASC', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            ARRAY_A
        ) ?: [];

        return array_map(fn (array $row): array => $this->formatRule($row), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . Database::tableName() . ' WHERE id = %d', $id), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            ARRAY_A
        );

        return is_array($row) ? $this->formatRule($row) : null;
    }

    /**
     * @param array<int, string> $terms
     * @return array<string, mixed>|\WP_Error
     */
    public function save(?int $id, array $terms): array|\WP_Error
    {
        global $wpdb;

        $terms = $this->cleanTerms($terms);

        if (count($terms) < 2) {
            return new \WP_Error('too_few_terms', __('Add at least two words that should be treated as synonyms.', 'typesense-search'), ['status' => 400]);
        }

        $hash = $this->hashTerms($terms);

        $existingId = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM ' . Database::tableName() . ' WHERE terms_hash = %s AND id <> %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $hash,
                $id ?: 0
            )
        );

        if ($existingId > 0) {
            return new \WP_Error('duplicate_terms', __('A synonym rule already exists for this group of words.', 'typesense-search'), ['status' => 409]);
        }

        $now = current_time('mysql', true);
        $data = [
            'updated_at'  => $now,
            'synced_at'   => null,
            'terms'       => wp_json_encode(array_values($terms)),
            'terms_hash'  => $hash,
            'sync_status' => 'pending',
            'sync_error'  => null,
        ];

        if ($id && $this->get($id) !== null) {
            $wpdb->update(Database::tableName(), $data, ['id' => $id]);
        } else {
            $data['created_at'] = $now;
            $wpdb->insert(Database::tableName(), $data);
            $id = (int) $wpdb->insert_id;
        }

        return $this->get((int) $id) ?: [];
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        $row     = $wpdb->get_row(
            $wpdb->prepare('SELECT synced_at FROM ' . Database::tableName() . ' WHERE id = %d', $id) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        $deleted = false !== $wpdb->delete(Database::tableName(), ['id' => $id], ['%d']);

        // Only mark remaining rules pending when the deleted rule had already
        // been pushed to Typesense. If it was never synced, the synonym set in
        // Typesense is unaffected and does not need to be re-synced.
        if ($deleted && $row !== null && $row->synced_at !== null) {
            $this->markAllPending();
        }

        return $deleted;
    }

    public function markAllPending(): void
    {
        global $wpdb;

        $wpdb->query("UPDATE " . Database::tableName() . " SET synced_at = NULL, sync_status = 'pending', sync_error = NULL"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    public function markSynced(): void
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . Database::tableName() . " SET synced_at = %s, sync_status = 'synced', sync_error = NULL", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                current_time('mysql', true)
            )
        );
    }

    public function markSyncError(string $message): void
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . Database::tableName() . " SET sync_status = 'error', sync_error = %s", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $this->truncate($message, 1000)
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRule(array $row): array
    {
        $terms = json_decode((string) ($row['terms'] ?? '[]'), true);

        return [
            'id'          => (int) $row['id'],
            'terms'       => is_array($terms) ? array_values(array_map('strval', $terms)) : [],
            'sync_status' => (string) $row['sync_status'],
            'sync_error'  => (string) ($row['sync_error'] ?? ''),
            'synced_at'   => (string) ($row['synced_at'] ?? ''),
            'updated_at'  => (string) $row['updated_at'],
        ];
    }

    /**
     * @param array<int, string> $terms
     * @return array<int, string>
     */
    private function cleanTerms(array $terms): array
    {
        $clean = [];
        foreach ($terms as $term) {
            $term = $this->cleanTerm((string) $term);
            if ($term !== '' && !in_array($term, $clean, true)) {
                $clean[] = $term;
            }
        }

        return $clean;
    }

    private function cleanTerm(string $term): string
    {
        $term = trim((string) preg_replace('/\s+/u', ' ', sanitize_text_field($term)));

        return $this->truncate($term, 191);
    }

    /**
     * Builds an order-independent hash identifying a group of terms, used to
     * detect duplicate synonym groups regardless of entry order or case.
     *
     * @param array<int, string> $terms
     */
    private function hashTerms(array $terms): string
    {
        $normalized = array_map(
            fn (string $term): string => $this->normalizeTerm($term),
            $terms
        );
        sort($normalized);

        return md5(implode('|', $normalized));
    }

    private function normalizeTerm(string $term): string
    {
        $term = trim((string) preg_replace('/\s+/u', ' ', $term));

        if (class_exists('Normalizer')) {
            $term = \Normalizer::normalize($term, \Normalizer::FORM_C) ?: $term;
        }

        return function_exists('mb_strtolower')
            ? mb_strtolower($term, 'UTF-8')
            : strtolower($term);
    }

    private function truncate(string $value, int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $length, 'UTF-8')
            : substr($value, 0, $length);
    }
}
