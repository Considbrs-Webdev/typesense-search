<?php

namespace TypesenseSearch\ExternalPages;

/**
 * Reads and writes external pages stored in WordPress.
 *
 * An external page is a searchable entry (title, content, URL) that has no
 * WordPress post, e.g. a booking system or a page on another domain.
 */
class Repository
{
    public const MAX_TITLE_LENGTH   = 191;
    public const MAX_CONTENT_LENGTH = 20000;
    public const MAX_URL_LENGTH     = 2083;

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

        return array_map(fn (array $row): array => $this->formatPage($row), $rows);
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

        return is_array($row) ? $this->formatPage($row) : null;
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function save(?int $id, string $title, string $content, string $url): array|\WP_Error
    {
        global $wpdb;

        $title   = $this->cleanTitle($title);
        $content = $this->cleanContent($content);
        $url     = $this->cleanUrl($url);

        if ($title === '') {
            return new \WP_Error('missing_title', __('Enter a title for the external page.', 'typesense-search'), ['status' => 400]);
        }

        if ($url === '') {
            return new \WP_Error('invalid_url', __('Enter a valid URL starting with http:// or https://.', 'typesense-search'), ['status' => 400]);
        }

        $now  = current_time('mysql', true);
        $data = [
            'updated_at'  => $now,
            'synced_at'   => null,
            'title'       => $title,
            'content'     => $content,
            'url'         => $url,
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

        return false !== $wpdb->delete(Database::tableName(), ['id' => $id], ['%d']);
    }

    public function markAllPending(): void
    {
        global $wpdb;

        $wpdb->query("UPDATE " . Database::tableName() . " SET synced_at = NULL, sync_status = 'pending', sync_error = NULL"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    public function markSynced(int $id): void
    {
        global $wpdb;

        $wpdb->update(
            Database::tableName(),
            [
                'synced_at'   => current_time('mysql', true),
                'sync_status' => 'synced',
                'sync_error'  => null,
            ],
            ['id' => $id]
        );
    }

    public function markSyncError(int $id, string $message): void
    {
        global $wpdb;

        $wpdb->update(
            Database::tableName(),
            [
                'sync_status' => 'error',
                'sync_error'  => $this->truncate($message, 1000),
            ],
            ['id' => $id]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPage(array $row): array
    {
        return [
            'id'          => (int) $row['id'],
            'title'       => (string) $row['title'],
            'content'     => (string) $row['content'],
            'url'         => (string) $row['url'],
            'sync_status' => (string) $row['sync_status'],
            'sync_error'  => (string) ($row['sync_error'] ?? ''),
            'synced_at'   => (string) ($row['synced_at'] ?? ''),
            'updated_at'  => (string) $row['updated_at'],
        ];
    }

    private function cleanTitle(string $title): string
    {
        $title = trim((string) preg_replace('/\s+/u', ' ', sanitize_text_field($title)));

        return $this->truncate($title, self::MAX_TITLE_LENGTH);
    }

    private function cleanContent(string $content): string
    {
        return $this->truncate(trim(sanitize_textarea_field($content)), self::MAX_CONTENT_LENGTH);
    }

    /**
     * Returns a sanitized absolute http(s) URL, or an empty string when the
     * input is not one.
     */
    private function cleanUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '' || !preg_match('#^https?://[^\s/]+#i', $url) || strlen($url) > self::MAX_URL_LENGTH) {
            return '';
        }

        return esc_url_raw($url, ['http', 'https']);
    }

    private function truncate(string $value, int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $length, 'UTF-8')
            : substr($value, 0, $length);
    }
}
