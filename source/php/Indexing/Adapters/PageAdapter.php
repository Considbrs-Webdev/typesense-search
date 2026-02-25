<?php

namespace TypesenseSearch\Indexing\Adapters;

use TypesenseSearch\Indexing\DocumentBuilder;

/**
 * Class PageAdapter
 *
 * Enriches page documents with a `top_most_parent` field that stores the title
 * of the top-level ancestor page. This allows the search UI to facet or filter
 * results by top-level site section.
 *
 * Hook: Municipio/TypesenseSearch/DocumentBuilder/page/build
 *
 * @package TypesenseSearch\Indexing\Adapters
 */
class PageAdapter
{
    public function __construct()
    {
        add_filter(
            sprintf(DocumentBuilder::FILTER_BUILD_POST_TYPE, 'page'),
            [$this, 'addTopMostParent'],
            10,
            2
        );
    }

    /**
     * Adds the `top_most_parent` field to the document.
     *
     * @param array<string, mixed> $document The document being built.
     * @param \WP_Post             $post     The source post object.
     * @return array<string, mixed>
     */
    public function addTopMostParent(array $document, \WP_Post $post): array
    {
        $ancestors = get_post_ancestors($post);

        // Use the top-most ancestor if available, otherwise fall back to the
        // page itself.
        $topMostParentPost = !empty($ancestors)
            ? get_post((int) end($ancestors))
            : $post;

        if ($topMostParentPost) {
            $document['top_most_parent'] = (string) $topMostParentPost->post_title;
        }

        return $document;
    }
}
