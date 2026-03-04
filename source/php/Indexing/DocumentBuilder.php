<?php

namespace TypesenseSearch\Indexing;

use TypesenseSearch\Admin\MetaBox;

/**
 * Class DocumentBuilder
 *
 * Transforms a WP_Post into the document array that gets upserted into
 * Typesense. The final array is passed through a WordPress filter so that
 * themes and plugins can add, remove or transform fields without touching
 * core plugin code.
 *
 * Usage:
 *   $document = DocumentBuilder::build($post);
 *
 * To add or modify fields:
 *   add_filter('Municipio/TypesenseSearch/DocumentBuilder/build', function (array $doc, WP_Post $post): array {
 *       $doc['author'] = get_the_author_meta('display_name', $post->post_author);
 *       return $doc;
 *   }, 10, 2);
 *
 * @package TypesenseSearch\Indexing
 */
class DocumentBuilder
{
    /**
     * Filter hook applied to the document array before it is indexed.
     * Receives: (array $document, WP_Post $post)
     */
    public const FILTER_BUILD = 'Municipio/TypesenseSearch/DocumentBuilder/build';

    /**
     * Dynamic filter hook pattern applied after FILTER_BUILD, scoped to the
     * post type. The placeholder %s is replaced with the post's post_type.
     * Receives: (array $document, WP_Post $post)
     *
     * Example for post_type "page":
     *   Municipio/TypesenseSearch/DocumentBuilder/page/build
     */
    public const FILTER_BUILD_POST_TYPE = 'Municipio/TypesenseSearch/DocumentBuilder/%s/build';

    /**
     * Default maximum excerpt length in characters. Can be filtered via
     * `Municipio/TypesenseSearch/DocumentBuilder/excerpt_length`.
     */
    public const DEFAULT_EXCERPT_LENGTH = 140;

    /**
     * Filter hook to modify the excerpt max length. Receives: (int $length, \WP_Post $post)
     */
    public const FILTER_EXCERPT_LENGTH = 'Municipio/TypesenseSearch/DocumentBuilder/excerpt_length';

    /**
     * Default truncator appended to trimmed excerpts. Can be filtered via
     * `Municipio/TypesenseSearch/DocumentBuilder/excerpt_truncator`.
     */
    public const DEFAULT_EXCERPT_TRUNCATOR = '[...]';

    /**
     * Filter hook to modify the truncator string. Receives: (string $truncator, \WP_Post $post)
     */
    public const FILTER_EXCERPT_TRUNCATOR = 'Municipio/TypesenseSearch/DocumentBuilder/excerpt_truncator';

    /**
     * Build a Typesense document array from a WP_Post object.
     *
     * @param \WP_Post $post The post to build the document for.
     * @return array<string, mixed>
     */
    public static function build(\WP_Post $post): array
    {
        $thumbnail = '';
        if (has_post_thumbnail($post->ID)) {
            $thumbnail = (string) get_the_post_thumbnail_url($post->ID, 'medium');
        }

        $dateTimestamp = (int) strtotime((string) $post->post_date_gmt);
        $document = [
            'id'                   => (string) $post->ID,
            'title'                => (string) $post->post_title,
            'content'              => wp_strip_all_tags((string) apply_filters('the_content', $post->post_content)),
            // Build a cleaned excerpt and trim it to the configured length.
            'excerpt'              => self::buildExcerpt(get_the_excerpt($post), $post),
            'url'                  => (string) get_permalink($post),
            'type'                 => (string) $post->post_type,
            'type_name'            => (string) get_post_type_object($post->post_type)->label,
            'date'                 => $dateTimestamp,
            'post_date_formatted'   => $dateTimestamp > 0 ? (string) date_i18n(get_option('date_format'), $dateTimestamp) : '',
            'thumbnail'            => $thumbnail,
            // Extra search terms entered via the meta box — stored as a plain
            // string so Typesense can tokenise and match against them.
            'extra_terms'          => (string) get_post_meta($post->ID, MetaBox::META_EXTRA_TERMS, true),
        ];

        /**
         * Filters the document array before it is sent to Typesense.
         *
         * @param array<string, mixed> $document The default document fields.
         * @param \WP_Post             $post     The source post object.
         */
        $document = (array) apply_filters(self::FILTER_BUILD, $document, $post);

        /**
         * Filters the document array for a specific post type before it is
         * sent to Typesense. The hook name is dynamic, e.g. for post_type
         * "page": Municipio/TypesenseSearch/DocumentBuilder/page/build
         *
         * @param array<string, mixed> $document The document fields.
         * @param \WP_Post             $post     The source post object.
         */
        return (array) apply_filters(
            sprintf(self::FILTER_BUILD_POST_TYPE, $post->post_type),
            $document,
            $post
        );
    }

    /**
     * Build a safe, trimmed excerpt string.
     *
     * @param string  $excerpt Raw excerpt to clean and trim.
     * @param \WP_Post $post  The source post object (passed to the length filter).
     * @return string
     */
    private static function buildExcerpt(string $excerpt, \WP_Post $post): string
    {
        $clean = wp_strip_all_tags((string) $excerpt);
        if ($clean === '') {
            return '';
        }

        $maxLength = (int) apply_filters(self::FILTER_EXCERPT_LENGTH, self::DEFAULT_EXCERPT_LENGTH, $post);
        if ($maxLength <= 0) {
            return $clean;
        }

        if (mb_strlen($clean) <= $maxLength) {
            return $clean;
        }

        $truncated = mb_substr($clean, 0, $maxLength);
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        $truncator = (string) apply_filters(self::FILTER_EXCERPT_TRUNCATOR, self::DEFAULT_EXCERPT_TRUNCATOR, $post);

        return rtrim($truncated) . ' ' . $truncator;
    }
}
