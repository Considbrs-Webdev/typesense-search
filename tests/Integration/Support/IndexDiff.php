<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Integration\Support;

use TypesenseSearch\Indexing\DocumentBuilder;

/**
 * Compare indexed Typesense documents with freshly built DocumentBuilder output.
 */
final class IndexDiff
{
    /** @var string[] Fields compared in integration tests (issue #11). */
    public const COMPARED_FIELDS = [
        'url',
        'path',
        'path_urls',
        'top_most_parent',
        'content',
    ];

    /**
     * @param array<string, mixed>|null $indexed  Document from Typesense (null if missing).
     * @param \WP_Post                  $post     Source post for expected document.
     * @return string[] Human-readable diff lines; empty when in sync.
     */
    public static function diffPost(?array $indexed, \WP_Post $post): array
    {
        $expected = DocumentBuilder::build($post)->toArray();
        return self::diffDocuments($indexed, $expected, (string) $post->ID);
    }

    /**
     * @param array<string, mixed>|null $indexed
     * @param array<string, mixed>      $expected
     * @return string[]
     */
    public static function diffDocuments(?array $indexed, array $expected, string $label): array
    {
        $lines = [];

        if ($indexed === null) {
            $lines[] = "post {$label}: document missing from Typesense index";
            foreach (self::COMPARED_FIELDS as $field) {
                $exp = self::fieldValue($expected, $field);
                if ($exp !== '' && $exp !== null) {
                    $lines[] = "  {$field}: expected " . self::formatValue($exp) . ', actual (missing)';
                }
            }

            return $lines;
        }

        foreach (self::COMPARED_FIELDS as $field) {
            $exp = self::fieldValue($expected, $field);
            $act = self::fieldValue($indexed, $field);
            if ($exp !== $act) {
                $lines[] = "post {$label} field {$field}: expected " . self::formatValue($exp)
                    . ', got ' . self::formatValue($act);
            }
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $document
     */
    private static function fieldValue(array $document, string $field): mixed
    {
        if (!array_key_exists($field, $document)) {
            return '';
        }

        $value = $document[$field];
        if ($value === null) {
            return '';
        }

        return is_string($value) ? $value : (string) $value;
    }

    private static function formatValue(mixed $value): string
    {
        if ($value === '') {
            return '(empty string)';
        }

        $text = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        if (strlen($text) > 120) {
            return '"' . substr($text, 0, 117) . '..."';
        }

        return '"' . $text . '"';
    }
}
