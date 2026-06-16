<?php

namespace TypesenseSearch\Indexing\Enrichers;

use TypesenseSearch\Admin\MetaBox;
use TypesenseSearch\Indexing\DocumentBuilder;

/**
 * Class PageEnricher
 *
 * Enriches page documents with two additional fields:
 *
 *   - `top_most_parent` — title of the top-level ancestor page, so the search
 *     UI can facet or filter results by site section.
 *
 *   - `path` — slash-separated breadcrumb string built from ancestor titles,
 *     enabling hierarchical or path-based search queries.
 *
 * Neither field is added for the site's front page.
 *
 * Hook: Municipio/TypesenseSearch/DocumentBuilder/page/build
 *
 * @package TypesenseSearch\Indexing\Enrichers
 */
class PageEnricher
{
    /**
     * Cached front page ID to avoid repeated get_option() calls during large
     * indexing runs.
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
     * @param array<string, mixed> $document
     * @param \WP_Post             $post
     * @return array<string, mixed>
     */
    public function addTopMostParent(array $document, \WP_Post $post): array
    {
        if ($this->frontPageId && (int) $post->ID === $this->frontPageId) {
            return $document;
        }

        $ancestors = get_post_ancestors($post);

        $topMostParentPost = !empty($ancestors)
            ? get_post((int) end($ancestors))
            : $post;

        if ($topMostParentPost) {
            $document['top_most_parent'] = $this->isExcludedAsSection($topMostParentPost)
                ? ''
                : (string) $topMostParentPost->post_title;
        }

        return $document;
    }

    /**
     * Check whether a page should be hidden from the section facet.
     *
     * @param \WP_Post $post
     * @return bool
     */
    private function isExcludedAsSection(\WP_Post $post): bool
    {
        return $post->post_type === 'page'
            && get_post_meta($post->ID, MetaBox::META_EXCLUDE_AS_SECTION, true) === '1';
    }

    /**
     * Adds a `path` field derived from ancestor page titles.
     * Empty string when the page has no ancestors (or is the front page).
     *
     * @param array<string, mixed> $document
     * @param \WP_Post             $post
     * @return array<string, mixed>
     */
    public function addPath(array $document, \WP_Post $post): array
    {
        if ($this->frontPageId && (int) $post->ID === $this->frontPageId) {
            $document['path'] = '';
            return $document;
        }

        $ancestors = get_post_ancestors($post);

        if (empty($ancestors)) {
            $document['path'] = '';
            return $document;
        }

        $segments = [];
        foreach (array_reverse($ancestors) as $ancestorId) {
            $ancestorPost = get_post((int) $ancestorId);
            if ($ancestorPost) {
                $segments[] = (string) $ancestorPost->post_title;
            }
        }

        $segments[] = (string) $post->post_title;

        $document['path'] = implode(' / ', $segments);

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
