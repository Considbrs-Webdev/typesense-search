<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Integration\Support;

/**
 * Build deterministic parent / child / grandchild page trees in WordPress.
 */
final class PageTreeFactory
{
    /**
     * @return array{parent: \WP_Post, child: \WP_Post, grandchild: \WP_Post}
     */
    public static function createThreeLevelTree(string $suffix = ''): array
    {
        $suffix = $suffix !== '' ? $suffix : (string) wp_generate_password(6, false, false);

        $parentId = wp_insert_post([
            'post_title'  => 'Section Root ' . $suffix,
            'post_name'   => 'section-root-' . strtolower($suffix),
            'post_status' => 'publish',
            'post_type'   => 'page',
            'post_content'=> 'Parent body ' . $suffix,
        ], true);
        self::assertPostId($parentId, 'parent');

        $childId = wp_insert_post([
            'post_title'  => 'Branch Page ' . $suffix,
            'post_name'   => 'branch-page-' . strtolower($suffix),
            'post_status' => 'publish',
            'post_type'   => 'page',
            'post_parent' => (int) $parentId,
            'post_content'=> 'Child body ' . $suffix,
        ], true);
        self::assertPostId($childId, 'child');

        $grandchildId = wp_insert_post([
            'post_title'  => 'Leaf Page ' . $suffix,
            'post_name'   => 'leaf-page-' . strtolower($suffix),
            'post_status' => 'publish',
            'post_type'   => 'page',
            'post_parent' => (int) $childId,
            'post_content'=> 'Grandchild body ' . $suffix,
        ], true);
        self::assertPostId($grandchildId, 'grandchild');

        clean_post_cache((int) $parentId);
        clean_post_cache((int) $childId);
        clean_post_cache((int) $grandchildId);

        return [
            'parent'     => get_post((int) $parentId),
            'child'      => get_post((int) $childId),
            'grandchild' => get_post((int) $grandchildId),
        ];
    }

    /**
     * @param int|\WP_Error $postId
     */
    private static function assertPostId(int|\WP_Error $postId, string $label): void
    {
        if (is_wp_error($postId)) {
            throw new \RuntimeException('Failed to create ' . $label . ' page: ' . $postId->get_error_message());
        }
        if ($postId <= 0) {
            throw new \RuntimeException('Failed to create ' . $label . ' page.');
        }
    }
}
