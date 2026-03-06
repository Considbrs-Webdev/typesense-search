<?php

namespace TypesenseSearch\Helper;

/**
 * Class ExcerptHelper
 *
 * Shared utility for building safe, length-constrained excerpt strings.
 *
 * Used by any indexing strategy that needs to produce an excerpt field
 * (posts, PDF attachments, etc.) without duplicating the trimming logic.
 *
 * The maximum excerpt length and truncation string are both filterable so
 * that themes and plugins can adjust them globally or per post:
 *
 *   // Change maximum length for all content.
 *   add_filter('Municipio/TypesenseSearch/DocumentBuilder/excerpt_length', fn() => 200);
 *
 *   // Use a custom truncation marker.
 *   add_filter('Municipio/TypesenseSearch/DocumentBuilder/excerpt_truncator', fn() => '…');
 *
 * @package TypesenseSearch\Helper
 */
class ExcerptHelper
{
    /**
     * Default maximum excerpt length in characters.
     */
    public const DEFAULT_LENGTH = 140;

    /**
     * Filter hook to modify the excerpt max length.
     * Receives: (int $length, \WP_Post $post)
     */
    public const FILTER_LENGTH = 'Municipio/TypesenseSearch/DocumentBuilder/excerpt_length';

    /**
     * Default truncation marker appended when the excerpt is cut.
     */
    public const DEFAULT_TRUNCATOR = '[...]';

    /**
     * Filter hook to modify the truncation string.
     * Receives: (string $truncator, \WP_Post $post)
     */
    public const FILTER_TRUNCATOR = 'Municipio/TypesenseSearch/DocumentBuilder/excerpt_truncator';

    /**
     * Build a safe, trimmed excerpt string from arbitrary text.
     *
     * Steps:
     *  1. Strip all HTML tags.
     *  2. Return as-is if already within the configured length.
     *  3. Trim to the last word boundary within the limit and append the
     *     truncation marker.
     *
     * @param string   $text Raw text (may contain HTML, will be stripped).
     * @param \WP_Post $post The source post — passed to the length and
     *                       truncator filters so callers can adjust values
     *                       per post type, taxonomy, etc.
     * @return string
     */
    public static function build(string $text, \WP_Post $post): string
    {
        $clean = wp_strip_all_tags($text);
        if ($clean === '') {
            return '';
        }

        $maxLength = (int) apply_filters(self::FILTER_LENGTH, self::DEFAULT_LENGTH, $post);
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

        $truncator = (string) apply_filters(self::FILTER_TRUNCATOR, self::DEFAULT_TRUNCATOR, $post);

        return rtrim($truncated) . ' ' . $truncator;
    }
}
