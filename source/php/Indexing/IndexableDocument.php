<?php

namespace TypesenseSearch\Indexing;

/**
 * Class IndexableDocument
 *
 * Immutable value object representing a document ready to be upserted into
 * Typesense. Strategies return this class from buildDocument() instead of a
 * plain array so that:
 *
 *   - The 'id' field (required by every Typesense document) is guaranteed to
 *     be present at construction time.
 *   - Strategies can receive a typed object rather than an untyped array.
 *   - The document can be inspected or enriched via with() without mutation.
 *
 * ── Relationship to DocumentBuilder filters ───────────────────────────────
 *
 * DocumentBuilder's WordPress filter chain (FILTER_BUILD, FILTER_BUILD_POST_TYPE)
 * still operates on plain arrays — existing filter callbacks are unaffected.
 * DocumentBuilder wraps its final array in IndexableDocument before returning.
 *
 * ── Custom strategies ─────────────────────────────────────────────────────
 *
 * Strategies that build their own document should return:
 *
 *   return new IndexableDocument([
 *       'id'    => (string) $post->ID,
 *       'title' => $post->post_title,
 *       // ...
 *   ]);
 *
 * @package TypesenseSearch\Indexing
 */
final class IndexableDocument
{
    /** @var array<string, mixed> */
    private array $fields;

    /**
     * @param array<string, mixed> $fields All document fields. Must include a
     *                                     non-empty 'id' and 'title' key.
     * @throws \InvalidArgumentException   When the 'id' or 'title' field is absent or empty.
     */
    public function __construct(array $fields)
    {
        if (empty($fields['id'])) {
            throw new \InvalidArgumentException(
                'IndexableDocument requires a non-empty "id" field (required by Typesense).'
            );
        }

        if (empty($fields['title'])) {
            throw new \InvalidArgumentException(
                'IndexableDocument requires a non-empty "title" field (required by Typesense).'
            );
        }

        $this->fields = $fields;
    }

    /**
     * Return a new instance with an additional or updated field.
     *
     * The original instance is not modified (value-object semantics).
     *
     * @param string $key   Field name.
     * @param mixed  $value Field value.
     * @return static
     */
    public function with(string $key, mixed $value): static
    {
        $clone         = clone $this;
        $clone->fields[$key] = $value;
        return $clone;
    }

    /**
     * Retrieve a single field value.
     *
     * @param string $key     Field name.
     * @param mixed  $default Returned when the field is absent.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->fields[$key] ?? $default;
    }

    /**
     * Return the document id.
     *
     * @return string
     */
    public function getId(): string
    {
        return (string) $this->fields['id'];
    }

    /**
     * Return all fields as a plain array, ready to pass to the Typesense
     * client's upsert / create call.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->fields;
    }
}
