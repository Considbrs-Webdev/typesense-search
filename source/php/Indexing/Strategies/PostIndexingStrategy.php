<?php

namespace TypesenseSearch\Indexing\Strategies;

use TypesenseSearch\Admin\MetaBox;
use TypesenseSearch\Admin\Settings;
use TypesenseSearch\Indexing\DocumentBuilder;
use TypesenseSearch\Indexing\IndexableDocument;

/**
 * Class PostIndexingStrategy
 *
 * Indexing strategy for standard WordPress post types (post, page, custom
 * post types — everything except attachments).
 *
 * Eligibility checks:
 *   - The post type is enabled in plugin settings.
 *   - The post status is 'publish'.
 *   - The `_typesense_exclude` meta flag is not set.
 *
 * Document building is delegated to DocumentBuilder, which applies two layers
 * of WordPress filters so that enrichers (PageEnricher, JobPostingEnricher,
 * etc.) and external code can add fields without modifying this class.
 *
 * The shouldIndex result is filterable via FILTER_SHOULD_INDEX.
 *
 * @package TypesenseSearch\Indexing\Strategies
 */
class PostIndexingStrategy extends AbstractIndexingStrategy
{
    /**
     * Filter hook that controls whether a post is eligible for indexing.
     * Receives: (bool $shouldIndex, WP_Post $post)
     *
     * The hook name intentionally retains the legacy "Indexer" segment so
     * that existing external hooks registered against this string continue to
     * work without changes.
     */
    public const FILTER_SHOULD_INDEX = 'Municipio/TypesenseSearch/Indexer/shouldIndex';

    /**
     * {@inheritdoc}
     */
    public function getIdentifier(): string
    {
        return 'post';
    }

    /**
     * {@inheritdoc}
     *
     * Returns true for any post that is NOT an attachment. Attachments are
     * handled by PdfIndexingStrategy (or not indexed at all).
     */
    public function supports(\WP_Post $post): bool
    {
        return $post->post_type !== 'attachment';
    }

    /**
     * {@inheritdoc}
     */
    public function shouldIndex(\WP_Post $post): bool
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
     * {@inheritdoc}
     *
     * Delegates to DocumentBuilder::build() which applies the standard
     * filter chain (FILTER_BUILD, FILTER_BUILD_POST_TYPE) and returns an
     * IndexableDocument.
     */
    public function buildDocument(\WP_Post $post): IndexableDocument|false
    {
        return DocumentBuilder::build($post);
    }
}
