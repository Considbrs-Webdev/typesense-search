<?php

namespace TypesenseSearch\Indexing;

use TypesenseSearch\Indexing\Strategies\PdfIndexingStrategy;

/**
 * Class IndexingHooks
 *
 * Wires WordPress lifecycle events to the IndexingRegistry so posts are kept
 * in sync with the Typesense collection automatically.
 *
 * This class is content-type agnostic — it does not contain any logic specific
 * to posts, PDFs, or other content types. Instead, it uses the registry to
 * resolve the correct IndexingStrategyInterface implementation for each post
 * and delegates all indexing decisions to that strategy.
 *
 * Covered generic events:
 *   - Post published or updated while published  → index (if shouldIndex passes)
 *   - Post unpublished (any status → non-publish) → deindex
 *   - Post moved to trash                         → deindex
 *   - Post permanently deleted                    → deindex
 *
 * Content-specific hooks (e.g. attachment events for PDFs) are registered by
 * each strategy via its registerHooks() method, called from the registry
 * during bootstrap.
 *
 * Hook choice: `wp_after_insert_post` (WP 5.6+) is used instead of
 * `transition_post_status` for save/update operations because it fires after
 * ALL meta boxes have written their values to the database. Using the earlier
 * hook would mean `shouldIndex()` reads stale meta on the first save.
 *
 * @package TypesenseSearch\Indexing
 */
class IndexingHooks
{
    private IndexingRegistry $registry;

    public function __construct(IndexingRegistry $registry)
    {
        $this->registry = $registry;

        // Let each strategy register its own content-specific hooks.
        $this->registry->registerAllHooks();

        // Fires after the post AND all its meta/terms are fully saved.
        // Priority 20 ensures any other save_post meta-writing (priority 10)
        // has already completed.
        add_action('wp_after_insert_post', [$this, 'onAfterInsertPost'], 20, 4);

        // Fires when a post is trashed (moved to Trash, not yet deleted).
        add_action('trashed_post', [$this, 'onPostDeindexed']);

        // Fires just before a post is permanently deleted from the database.
        add_action('before_delete_post', [$this, 'onPostDeindexed']);
    }

    /**
     * Triggered after a post and all its meta are fully saved.
     *
     * Uses the registry to find the correct strategy for the post, then
     * delegates shouldIndex/index/deindex to that strategy.
     *
     * @param int            $post_id   Post ID.
     * @param \WP_Post       $post      Post object in its new state.
     * @param bool           $update    Whether this is an existing post being updated.
     * @param \WP_Post|null  $postBefore Post object before the update, or null for new posts.
     */
    public function onAfterInsertPost(int $post_id, \WP_Post $post, bool $update, null|\WP_Post $postBefore): void
    {
        $strategy  = $this->registry->resolve($post);
        if ($strategy === null) {
            return;
        }

        $newStatus = $post->post_status;
        $oldStatus = $postBefore ? $postBefore->post_status : 'new';

        if ($newStatus === 'publish') {
            if ($strategy->shouldIndex($post)) {
                $strategy->index($post);
            } else {
                // Post is published but shouldIndex returned false (e.g. user
                // ticked "Exclude from index") — ensure it is removed.
                $strategy->deindex($post->ID);
            }

            // Cascade: re-index any PDF attachments directly attached to this
            // post so their top_most_parent field stays in sync.
            $this->cascadePdfReindex($post->ID);

            return;
        }

        // Transitioning away from published (unpublishing) → remove from index.
        if ($oldStatus === 'publish') {
            $strategy->deindex($post->ID);
        }
    }

    /**
     * Triggered when a post is trashed or permanently deleted.
     *
     * Attempts deindex via every registered strategy. This is intentionally
     * broad — a post might have been indexed under a different strategy when
     * settings were different, and the Typesense document ID is the WP post
     * ID regardless of strategy.
     *
     * In practice, the abstract base class's deindex() treats "not found" as
     * success, so the extra calls are effectively no-ops.
     *
     * @param int $postId WordPress post ID.
     */
    public function onPostDeindexed(int $postId): void
    {
        // The document ID is the WP post ID, shared across all strategies in
        // the same Typesense collection. A single deindex call is sufficient.
        $post = get_post($postId);
        if ($post) {
            $strategy = $this->registry->resolve($post);
            if ($strategy) {
                $strategy->deindex($postId);
                return;
            }
        }

        // Fallback: if the post is already gone or no strategy matches,
        // try the first available strategy (they all share the same collection).
        $all = $this->registry->all();
        if (!empty($all)) {
            reset($all)->deindex($postId);
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Ask the PdfIndexingStrategy (if registered) to re-index PDFs attached
     * to the given parent post. Keeps the top_most_parent field in sync when
     * a page title changes.
     *
     * @param int $postId Parent post ID.
     */
    private function cascadePdfReindex(int $postId): void
    {
        $pdfStrategy = $this->registry->get('pdf');
        if ($pdfStrategy instanceof PdfIndexingStrategy) {
            $pdfStrategy->reindexAttachedPdfs($postId);
        }
    }
}

