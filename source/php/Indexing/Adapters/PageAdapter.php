<?php

namespace TypesenseSearch\Indexing\Adapters;

use TypesenseSearch\Indexing\DocumentBuilder;

/**
 * Class PageAdapter
 *
 * Enriches page documents with a `top_most_parent` field that stores the title
 * of the top-level ancestor page and a `path` field built from ancestor slugs.
 * This allows the search UI to facet or filter results by top-level site
 * section and to perform hierarchical/path-based searches.
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
        add_filter(
            sprintf(DocumentBuilder::FILTER_BUILD_POST_TYPE, 'page'),
            [$this, 'addPath'],
            10,
            2
        );
        add_filter(
            sprintf(DocumentBuilder::FILTER_BUILD_POST_TYPE, 'page'),
            [$this, 'addPathUrls'],
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

        // top_most_parent logic only; `path` is handled by a separate filter.

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

    /**
     * Adds a `path` field to the document derived from ancestor slugs.
     * If there are no ancestors the field will be an empty string.
     *
     * @param array<string, mixed> $document
     * @param \WP_Post $post
     * @return array<string, mixed>
     */
    public function addPath(array $document, \WP_Post $post): array
    {
        $ancestors = get_post_ancestors($post);

        // Do not add a `path` for the site's front page.
        if ($this->frontPageId && isset($post->ID) && (int) $post->ID === $this->frontPageId) {
            $document['path'] = '';
            return $document;
        }

        $document['path'] = '';
        if (!empty($ancestors)) {
            $ancestors = array_reverse($ancestors);
            $segments = [];
            foreach ($ancestors as $ancestorId) {
                $ancestorPost = get_post((int) $ancestorId);
                if ($ancestorPost && isset($ancestorPost->post_title)) {
                    $segments[] = (string) $ancestorPost->post_title;
                }
            }

            $segments[] = (string) $post->post_title;

            $document['path'] = implode(' / ', $segments);
        }

        return $document;
    }

    /**
     * Adds a `path_urls` field — a pipe-separated list of permalink URLs for
     * each breadcrumb segment, in the same order as `path`.
     * Used by the frontend to render clickable breadcrumbs.
     *
     * @param array<string, mixed> $document
     * @param \WP_Post $post
     * @return array<string, mixed>
     */
    public function addPathUrls(array $document, \WP_Post $post): array
    {
        if ($this->frontPageId && isset($post->ID) && (int) $post->ID === $this->frontPageId) {
            $document['path_urls'] = '';
            return $document;
        }

        $ancestors = get_post_ancestors($post);
        $document['path_urls'] = '';

        if (!empty($ancestors)) {
            $ancestors  = array_reverse($ancestors);
            $urls       = [];

            foreach ($ancestors as $ancestorId) {
                $url = get_permalink((int) $ancestorId);
                if ($url) {
                    $urls[] = (string) $url;
                }
            }

            $url = get_permalink($post->ID);
            if ($url) {
                $urls[] = (string) $url;
            }

            $document['path_urls'] = implode(' | ', $urls);
        }

        return $document;
    }
}
