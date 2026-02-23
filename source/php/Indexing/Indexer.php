<?php

namespace TypesenseSearch\Indexing;

use TypesenseSearch\Admin\MetaBox;
use TypesenseSearch\Admin\Settings;
use TypesenseSearch\Typesense\ClientFactory;

/**
 * Class Indexer
 *
 * Responsible for upserting and removing documents in Typesense.
 *
 * Central shouldIndex() guard runs all eligibility checks so that every
 * code path goes through the same logic. The result can be overridden via
 * the `Municipio/TypesenseSearch/Indexer/shouldIndex` filter.
 *
 * Usage in an external hook:
 *   if (Indexer::shouldIndex($post)) {
 *       Indexer::index($post);
 *   }
 *
 * @package TypesenseSearch\Indexing
 */
class Indexer
{
    /**
     * Filter hook that controls whether a post is eligible for indexing.
     * Receives: (bool $shouldIndex, WP_Post $post)
     */
    public const FILTER_SHOULD_INDEX = 'Municipio/TypesenseSearch/Indexer/shouldIndex';

    /**
     * Determine whether a post should be present in the Typesense index.
     *
     * Returns false when:
     *   - The post type is not enabled in plugin settings.
     *   - The post status is not 'publish'.
     *   - The `_typesense_exclude` meta flag is set to '1'.
     *
     * The return value is passed through the `Municipio/TypesenseSearch/Indexer/shouldIndex`
     * filter so developers can add their own conditions.
     *
     * @param \WP_Post $post Post to evaluate.
     */
    public static function shouldIndex(\WP_Post $post): bool
    {
        $result = Settings::isPostTypeEnabled($post->post_type)
            && $post->post_status === 'publish'
            && get_post_meta($post->ID, MetaBox::META_EXCLUDE, true) !== '1';

        /**
         * Filters whether the given post should be indexed in Typesense.
         *
         * @param bool     $result Whether the post qualifies for indexing.
         * @param \WP_Post $post   The post being evaluated.
         */
        return (bool) apply_filters(self::FILTER_SHOULD_INDEX, $result, $post);
    }

    /**
     * Upsert a post document into Typesense.
     *
     * Silently returns false when the connection is unavailable or the
     * collection name is not set, so indexing failures never break a
     * page save for the editor.
     *
     * @param \WP_Post $post Post to index.
     * @return bool True on success, false on failure.
     */
    public static function index(\WP_Post $post): bool
    {
        $client         = ClientFactory::fromOptions();
        $collectionName = (string) get_option(Settings::OPTION_INDEX_NAME, '');

        if ($client === null || $collectionName === '') {
            return false;
        }

        try {
            $document = DocumentBuilder::build($post);
            $client->collections[$collectionName]->documents->upsert($document);
            return true;
        } catch (\Exception $e) {
            // Log but do not surface to the editor — indexing is best-effort.
            error_log(sprintf(
                '[TypesenseSearch] Failed to index post %d: %s',
                $post->ID,
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Remove a document from the Typesense index by post ID.
     *
     * Silently returns false when the connection is unavailable.
     *
     * @param int $postId The WordPress post ID to deindex.
     * @return bool True on success, false on failure.
     */
    public static function deindex(int $postId): bool
    {
        $client         = ClientFactory::fromOptions();
        $collectionName = (string) get_option(Settings::OPTION_INDEX_NAME, '');

        if ($client === null || $collectionName === '') {
            return false;
        }

        try {
            $client->collections[$collectionName]->documents[(string) $postId]->delete();
            return true;
        } catch (\Exception $e) {
            error_log(sprintf(
                '[TypesenseSearch] Failed to deindex post %d: %s',
                $postId,
                $e->getMessage()
            ));
            return false;
        }
    }
}
