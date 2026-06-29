<?php

namespace TypesenseSearch\Admin\Ajax;

use TypesenseSearch\Admin\SettingsAjax;
use TypesenseSearch\Logger\IndexingLog;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Typesense\ClientFactory;

/**
 * AJAX handlers for collection statistics, per-type clearing, and per-type re-indexing.
 *
 * Covers:
 *   - typesense_get_stats          (handleGetStats)
 *   - typesense_clear_post_type    (handleClearPostType)
 *   - typesense_reindex_post_type  (handleReindexPostType)
 *
 * @package TypesenseSearch\Admin\Ajax
 */
class IndexingActions
{
    use AjaxHelpers;

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function register(): void
    {
        add_action('wp_ajax_' . SettingsAjax::AJAX_ACTION_GET_STATS,          [$this, 'handleGetStats']);
        add_action('wp_ajax_' . SettingsAjax::AJAX_ACTION_CLEAR_POST_TYPE,    [$this, 'handleClearPostType']);
        add_action('wp_ajax_' . SettingsAjax::AJAX_ACTION_REINDEX_POST_TYPE,  [$this, 'handleReindexPostType']);
    }

    // ── 1. Get collection statistics ─────────────────────────────────────────

    public function handleGetStats(): void
    {
        $this->requirePermission(SettingsAjax::AJAX_ACTION_GET_STATS);

        $remote         = $this->settings->getRemote();
        $adminKey       = $this->settings->getAdminKey();
        $collectionName = $this->settings->getCollectionName();

        if (empty($remote) || empty($adminKey) || empty($collectionName)) {
            wp_send_json_error(['message' => __('Connection settings are incomplete. Please configure the connection first.', 'typesense-search')]);
            return;
        }

        try {
            $client = ClientFactory::build($remote, $adminKey);

            $result = $client->collections[$collectionName]->documents->search([
                'q'                => '*',
                'query_by'         => 'title',
                'facet_by'         => 'type,type_name',
                'max_facet_values' => 100,
                'per_page'         => 0,
            ]);

            $total  = $result['found'] ?? 0;
            $facets = [];

            // Build a position-indexed map of type_name values. Because type and
            // type_name have a strict 1:1 relationship per document, Typesense
            // returns both facet groups in the same count-descending order, so
            // the N-th type entry corresponds to the N-th type_name entry.
            $typeNamesByPosition = [];
            foreach ($result['facet_counts'] ?? [] as $facetGroup) {
                if (($facetGroup['field_name'] ?? '') === 'type_name') {
                    foreach ($facetGroup['counts'] ?? [] as $i => $item) {
                        $typeNamesByPosition[$i] = $item['value'];
                    }
                    break;
                }
            }

            foreach ($result['facet_counts'] ?? [] as $facetGroup) {
                if (($facetGroup['field_name'] ?? '') === 'type') {
                    $externalIds = array_keys(\TypesenseSearch\App::getRegistry()->allExternal());

                    foreach ($facetGroup['counts'] ?? [] as $i => $item) {
                        $slug        = $item['value'];
                        // Prefer the registered WP label; fall back to the stored
                        // type_name (correct for external services), then the slug.
                        $postTypeObj = get_post_type_object($slug);
                        $label       = $postTypeObj
                            ? $postTypeObj->label
                            : ($typeNamesByPosition[$i] ?? $slug);

                        $facets[] = [
                            'slug'     => $slug,
                            'label'    => $label,
                            'count'    => (int) $item['count'],
                            'external' => in_array($slug, $externalIds, true),
                        ];
                    }
                }
            }

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: error message */
                    __('Could not load statistics: %s', 'typesense-search'),
                    $e->getMessage()
                ),
            ]);
            return;
        }

        wp_send_json_success([
            'total'          => $total,
            'collectionName' => $collectionName,
            'facets'         => $facets,
        ]);
    }

    // ── 2. Clear post type from index ────────────────────────────────────────

    public function handleClearPostType(): void
    {
        $this->requirePermission(SettingsAjax::AJAX_ACTION_CLEAR_POST_TYPE);

        $postType = sanitize_key(wp_unslash($_POST['post_type'] ?? ''));

        if (empty($postType)) {
            wp_send_json_error(['message' => __('Post type is required.', 'typesense-search')]);
            return;
        }

        $remote         = $this->settings->getRemote();
        $adminKey       = $this->settings->getAdminKey();
        $collectionName = $this->settings->getCollectionName();

        if (empty($remote) || empty($adminKey) || empty($collectionName)) {
            wp_send_json_error(['message' => __('Connection settings are incomplete.', 'typesense-search')]);
            return;
        }

        try {
            $client = ClientFactory::build($remote, $adminKey);
            $result = $client->collections[$collectionName]->documents->delete([
                'filter_by' => 'type:=' . $postType,
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: error message */
                    __('Could not clear post type: %s', 'typesense-search'),
                    $e->getMessage()
                ),
            ]);
            return;
        }

        wp_send_json_success([
            'message' => sprintf(
                /* translators: 1: number of deleted docs, 2: post type slug */
                __('Removed %1$d documents of type "%2$s" from the index.', 'typesense-search'),
                $result['num_deleted'] ?? 0,
                $postType
            ),
            'deleted'  => $result['num_deleted'] ?? 0,
            'postType' => $postType,
        ]);
    }

    // ── 3. Reindex post type ─────────────────────────────────────────────────

    public function handleReindexPostType(): void
    {
        $this->requirePermission(SettingsAjax::AJAX_ACTION_REINDEX_POST_TYPE);

        $postType = sanitize_key(wp_unslash($_POST['post_type'] ?? ''));

        if (empty($postType)) {
            wp_send_json_error(['message' => __('Post type is required.', 'typesense-search')]);
            return;
        }

        // Allow the indexing loop to run to completion regardless of request
        // timeout or client disconnect — important for large sites.
        ignore_user_abort(true);
        set_time_limit(0);

        $indexed  = 0;
        $skipped  = 0;
        $failed   = 0;
        $registry = \TypesenseSearch\App::getRegistry();
        IndexingLog::beginRun('admin', sprintf(
            /* translators: %s: post type slug */
            __('Manual reindex: %s', 'typesense-search'),
            $postType
        ));

        try {
            if ($postType === 'attachment') {
                // PDF attachment reindex
                $strategy = $registry->get('pdf');

                if ($strategy === null) {
                    IndexingLog::endRun([
                        'indexed' => 0,
                        'skipped' => 0,
                        'failed'  => 1,
                    ], __('PDF indexing strategy is not available.', 'typesense-search'));
                    wp_send_json_error(['message' => __('PDF indexing strategy is not available.', 'typesense-search')]);
                    return;
                }

                $offset    = 0;
                $batchSize = 50;

                do {
                    $posts = get_posts([
                        'post_type'        => 'attachment',
                        'post_status'      => 'inherit',
                        'post_mime_type'   => 'application/pdf',
                        'posts_per_page'   => $batchSize,
                        'offset'           => $offset,
                        'orderby'          => 'ID',
                        'order'            => 'ASC',
                        'suppress_filters' => false,
                    ]);

                    foreach ($posts as $post) {
                        try {
                            if (!$strategy->shouldIndex($post)) {
                                $skipped++;
                                continue;
                            }

                            $strategy->index($post) ? $indexed++ : $failed++;
                        } catch (\Throwable $e) {
                            $failed++;
                            IndexingLog::recordIssue('error', $e->getMessage(), [
                                'strategy'       => 'pdf',
                                'document_id'    => (string) $post->ID,
                                'document_label' => sprintf('%s (#%d)', (string) $post->post_title, $post->ID),
                            ]);
                        }
                    }

                    $offset += $batchSize;
                } while (count($posts) === $batchSize);
            } else {
                // Standard published post type reindex
                $offset    = 0;
                $batchSize = 50;

                do {
                    $posts = get_posts([
                        'post_type'        => $postType,
                        'post_status'      => 'publish',
                        'posts_per_page'   => $batchSize,
                        'offset'           => $offset,
                        'orderby'          => 'ID',
                        'order'            => 'ASC',
                        'suppress_filters' => false,
                    ]);

                    foreach ($posts as $post) {
                        $strategy = null;

                        try {
                            $strategy = $registry->resolve($post);

                            if (!$strategy || !$strategy->shouldIndex($post)) {
                                $skipped++;
                                continue;
                            }

                            $strategy->index($post) ? $indexed++ : $failed++;
                        } catch (\Throwable $e) {
                            $failed++;
                            IndexingLog::recordIssue('error', $e->getMessage(), [
                                'strategy'       => $strategy ? $strategy->getIdentifier() : $postType,
                                'document_id'    => (string) $post->ID,
                                'document_label' => sprintf('%s (#%d)', (string) $post->post_title, $post->ID),
                            ]);
                        }
                    }

                    $offset += $batchSize;
                } while (count($posts) === $batchSize);
            }
        } catch (\Exception $e) {
            IndexingLog::endRun([
                'indexed' => $indexed,
                'skipped' => $skipped,
                'failed'  => max(1, $failed),
            ], sprintf(
                /* translators: %s: error message */
                __('Reindex failed: %s', 'typesense-search'),
                $e->getMessage()
            ));

            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: error message */
                    __('Reindex failed: %s', 'typesense-search'),
                    $e->getMessage()
                ),
            ]);
            return;
        }

        IndexingLog::endRun([
            'indexed' => $indexed,
            'skipped' => $skipped,
            'failed'  => $failed,
        ], sprintf(
            /* translators: 1: indexed count, 2: skipped count, 3: failed count, 4: post type slug */
            __('Reindexed "%4$s": %1$d indexed, %2$d skipped, %3$d failed.', 'typesense-search'),
            $indexed,
            $skipped,
            $failed,
            $postType
        ));

        wp_send_json_success([
            /* translators: 1: indexed count, 2: skipped count, 3: failed count, 4: post type slug */
            'message'  => sprintf(
                __('Reindexed "%4$s": %1$d indexed, %2$d skipped, %3$d failed.', 'typesense-search'),
                $indexed,
                $skipped,
                $failed,
                $postType
            ),
            'indexed'  => $indexed,
            'skipped'  => $skipped,
            'failed'   => $failed,
            'postType' => $postType,
        ]);
    }
}
