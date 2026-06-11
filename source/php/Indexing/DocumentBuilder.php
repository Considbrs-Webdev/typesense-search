<?php

namespace TypesenseSearch\Indexing;

use TypesenseSearch\Admin\MetaBox;
use TypesenseSearch\Helper\ExcerptHelper;
use TypesenseSearch\Indexing\IndexableDocument;

/**
 * Class DocumentBuilder
 *
 * Assembles the Typesense document array for a standard WordPress post and
 * passes it through two layers of WordPress filters so that themes and
 * plugins can add, remove, or transform fields without touching core code:
 *
 *   1. FILTER_BUILD  — applied to every post, regardless of post type.
 *   2. FILTER_BUILD_POST_TYPE — applied only to a specific post type.
 *
 * ── Lightweight enrichment (no new strategy needed) ──────────────────────
 *
 * For simple field additions or transformations you do NOT need to register a
 * new indexing strategy. Use the filters directly:
 *
 *   // Add a field to all indexed posts.
 *   add_filter('Municipio/TypesenseSearch/DocumentBuilder/build',
 *       function (array $doc, WP_Post $post): array {
 *           $doc['author'] = get_the_author_meta('display_name', $post->post_author);
 *           return $doc;
 *       }, 10, 2);
 *
 *   // Add a field only to a specific post type (e.g. "event").
 *   add_filter('Municipio/TypesenseSearch/DocumentBuilder/event/build',
 *       function (array $doc, WP_Post $post): array {
 *           $doc['event_date'] = get_post_meta($post->ID, '_event_date', true);
 *           return $doc;
 *       }, 10, 2);
 *
 * ── Register a new indexing strategy from outside ────────────────────────
 *
 * For content that needs completely custom shouldIndex/buildDocument logic
 * (e.g. a new content type with its own eligibility rules), register a
 * strategy from your theme or plugin:
 *
 *   add_action('Municipio/TypesenseSearch/RegisterStrategies',
 *       function (\TypesenseSearch\Indexing\IndexingRegistry $registry): void {
 *           $registry->register(new MyCustomStrategy());
 *       });
 *
 * @package TypesenseSearch\Indexing
 */
class DocumentBuilder
{
    /**
     * Filter hook applied to the document array for every post type.
     * Receives: (array $document, WP_Post $post)
     */
    public const FILTER_BUILD = 'Municipio/TypesenseSearch/DocumentBuilder/build';

    /**
     * Dynamic filter hook pattern scoped to a specific post type.
     * The placeholder %s is replaced with the post's post_type value.
     * Receives: (array $document, WP_Post $post)
     *
     * Example for post_type "page":
     *   Municipio/TypesenseSearch/DocumentBuilder/page/build
     */
    public const FILTER_BUILD_POST_TYPE = 'Municipio/TypesenseSearch/DocumentBuilder/%s/build';

    /**
     * Build a Typesense document for a WordPress post.
     *
     * The document passes through two WordPress filter layers (FILTER_BUILD
     * and FILTER_BUILD_POST_TYPE) that receive and return plain arrays — all
     * existing filter callbacks continue to work unchanged. The final array is
     * wrapped in an IndexableDocument before being returned.
     *
     * @param \WP_Post $post The post to build the document for.
     * @return IndexableDocument
     */
    public static function build(\WP_Post $post): IndexableDocument
    {
        $thumbnail = '';
        if (has_post_thumbnail($post->ID)) {
            $thumbnail = (string) get_the_post_thumbnail_url($post->ID, 'medium');
        }

        $dateTimestamp = (int) strtotime((string) (!empty($post->post_modified_gmt) ? $post->post_modified_gmt : $post->post_date_gmt));

        $document = [
            'id'                  => (string) $post->ID,
            'title'               => (string) $post->post_title,
            'content'             => wp_strip_all_tags((string) apply_filters('the_content', $post->post_content)),
            'excerpt'             => ExcerptHelper::build(get_the_excerpt($post), $post),
            'url'                 => (string) get_permalink($post),
            'type'                => (string) $post->post_type,
            'type_name'           => (string) get_post_type_object($post->post_type)->label,
            'date'                => $dateTimestamp,
            'post_date_formatted' => $dateTimestamp > 0
                ? (string) date_i18n(get_option('date_format'), $dateTimestamp)
                : '',
            'thumbnail'           => $thumbnail,
            'extra_terms'         => (string) get_post_meta($post->ID, MetaBox::META_EXTRA_TERMS, true),
        ];

        /**
         * Filters the document array before it is sent to Typesense.
         *
         * @param array<string, mixed> $document The default document fields.
         * @param \WP_Post             $post     The source post object.
         */
        $document = (array) apply_filters(self::FILTER_BUILD, $document, $post);

        /**
         * Filters the document array for a specific post type.
         * Hook name example for post_type "page":
         *   Municipio/TypesenseSearch/DocumentBuilder/page/build
         *
         * @param array<string, mixed> $document The document fields.
         * @param \WP_Post             $post     The source post object.
         */
        $document = (array) apply_filters(
            sprintf(self::FILTER_BUILD_POST_TYPE, $post->post_type),
            $document,
            $post
        );

        return new IndexableDocument($document);
    }
}

