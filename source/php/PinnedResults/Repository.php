<?php

namespace TypesenseSearch\PinnedResults;

use TypesenseSearch\Admin\Settings;

/**
 * Reads and writes pinned search-result rules stored in WordPress.
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
            'SELECT * FROM ' . Database::tableName() . ' ORDER BY phrase ASC, id ASC', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
     * @param array<int, int|string> $postIds
     * @return array<string, mixed>|\WP_Error
     */
    public function save(?int $id, string $phrase, string $matchType, array $postIds, bool $enabled = true): array|\WP_Error
    {
        global $wpdb;

        $phrase = $this->cleanPhrase($phrase);
        $normalized = $this->normalizePhrase($phrase);
        $matchType = $matchType === 'contains' ? 'contains' : 'exact';
        $postIds = $this->cleanPostIds($postIds);

        if ($normalized === '') {
            return new \WP_Error('empty_phrase', __('Add a search phrase before saving.', 'typesense-search'), ['status' => 400]);
        }

        if (empty($postIds)) {
            return new \WP_Error('empty_posts', __('Add at least one result to pin.', 'typesense-search'), ['status' => 400]);
        }

        $existingId = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM ' . Database::tableName() . ' WHERE normalized_phrase = %s AND id <> %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $normalized,
                $id ?: 0
            )
        );

        if ($existingId > 0) {
            return new \WP_Error('duplicate_phrase', __('A pinned result rule already exists for this phrase.', 'typesense-search'), ['status' => 409]);
        }

        $now = current_time('mysql', true);
        $data = [
            'updated_at'        => $now,
            'synced_at'         => null,
            'phrase'            => $phrase,
            'normalized_phrase' => $normalized,
            'match_type'        => $matchType,
            'items'             => wp_json_encode([
                'enabled'  => $enabled,
                'post_ids' => array_values($postIds),
            ]),
            'sync_status'       => 'pending',
            'sync_error'        => null,
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

        $deleted = false !== $wpdb->delete(Database::tableName(), ['id' => $id], ['%d']);
        if ($deleted) {
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
     * @return array<int, array<string, mixed>>
     */
    public function searchPosts(string $search, int $limit = 20): array
    {
        $search = $this->truncate(sanitize_text_field($search), 100);
        if ($this->characterLength(trim($search)) < 2) {
            return [];
        }

        $postTypes = array_values(array_filter((array) get_option(Settings::OPTION_POST_TYPES, [])));
        if (empty($postTypes)) {
            $postTypes = array_keys(Settings::getIndexablePostTypes());
        }

        $query = new \WP_Query([
            'post_type'              => $postTypes,
            'post_status'            => 'publish',
            's'                      => $search,
            'posts_per_page'         => max(1, min(50, $limit)),
            'orderby'                => 'relevance',
            'order'                  => 'DESC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        return array_map(fn (\WP_Post $post): array => $this->formatPost($post), $query->posts);
    }

    /**
     * @param array<int, int> $postIds
     * @return array<int, array<string, mixed>>
     */
    private function hydratePosts(array $postIds): array
    {
        $posts = [];
        foreach ($postIds as $postId) {
            $post = get_post($postId);
            if ($post instanceof \WP_Post) {
                $posts[] = $this->formatPost($post);
            }
        }

        return $posts;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRule(array $row): array
    {
        $items = json_decode((string) ($row['items'] ?? '[]'), true);
        $enabled = true;
        if (is_array($items) && array_key_exists('post_ids', $items)) {
            $enabled = !array_key_exists('enabled', $items) || (bool) $items['enabled'];
            $postIds = is_array($items['post_ids'] ?? null) ? $this->cleanPostIds($items['post_ids']) : [];
        } else {
            $postIds = is_array($items) ? $this->cleanPostIds($items) : [];
        }

        return [
            'id'                => (int) $row['id'],
            'phrase'            => (string) $row['phrase'],
            'normalized_phrase' => (string) $row['normalized_phrase'],
            'match_type'        => (string) $row['match_type'],
            'enabled'           => $enabled,
            'post_ids'          => $postIds,
            'posts'             => $this->hydratePosts($postIds),
            'sync_status'       => (string) $row['sync_status'],
            'sync_error'        => (string) ($row['sync_error'] ?? ''),
            'synced_at'         => (string) ($row['synced_at'] ?? ''),
            'updated_at'        => (string) $row['updated_at'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPost(\WP_Post $post): array
    {
        $postType = get_post_type_object($post->post_type);

        return [
            'id'        => (int) $post->ID,
            'title'     => html_entity_decode(get_the_title($post), ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8'),
            'type'      => (string) $post->post_type,
            'type_name' => $postType ? (string) $postType->labels->singular_name : (string) $post->post_type,
            'edit_url'  => get_edit_post_link($post->ID, 'raw') ?: '',
        ];
    }

    /**
     * @param array<int, int|string> $postIds
     * @return array<int, int>
     */
    private function cleanPostIds(array $postIds): array
    {
        $clean = [];
        foreach ($postIds as $postId) {
            $postId = absint($postId);
            if ($postId > 0 && !in_array($postId, $clean, true)) {
                $clean[] = $postId;
            }
        }

        return $clean;
    }

    private function cleanPhrase(string $phrase): string
    {
        $phrase = trim((string) preg_replace('/\s+/u', ' ', sanitize_text_field($phrase)));

        return $this->truncate($phrase, 191);
    }

    private function normalizePhrase(string $phrase): string
    {
        $phrase = trim((string) preg_replace('/\s+/u', ' ', $phrase));

        if (class_exists('Normalizer')) {
            $phrase = \Normalizer::normalize($phrase, \Normalizer::FORM_C) ?: $phrase;
        }

        return function_exists('mb_strtolower')
            ? mb_strtolower($phrase, 'UTF-8')
            : strtolower($phrase);
    }

    private function truncate(string $value, int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $length, 'UTF-8')
            : substr($value, 0, $length);
    }

    private function characterLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
