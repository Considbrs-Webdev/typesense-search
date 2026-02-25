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
    /**
     * Cached front page ID to avoid repeated calls to get_option() during
     * large indexing runs.
     *
     * @var int
     */
    private int $frontPageId = 0;
    public function __construct()
    {
        $this->frontPageId = (int) get_option('page_on_front');
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

        // Do not add a `top_most_parent` for the site's front page.
        if ($this->frontPageId && isset($post->ID) && (int) $post->ID === $this->frontPageId) {
            return $document;
        }

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
