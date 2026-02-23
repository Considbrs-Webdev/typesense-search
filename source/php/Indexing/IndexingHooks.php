<?php

namespace TypesenseSearch\Indexing;

/**
 * Class IndexingHooks
 *
 * Wires WordPress lifecycle events to the Indexer so posts are kept in sync
 * with the Typesense collection automatically.
 *
 * Covered events:
 *   - Post published or updated while published  → index (if shouldIndex passes)
 *   - Post unpublished (any status → non-publish) → deindex
 *   - Post moved to trash                         → deindex
 *   - Post permanently deleted                    → deindex
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
    public function __construct()
    {
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
     * @param \WP_Post      $post       Post object in its new state.
     * @param bool          $update     Whether this is an existing post being updated.
     * @param \WP_Post|null $postBefore Post object before the update, or null for new posts.
     */
    public function onAfterInsertPost(int $post_id, \WP_Post $post, bool $update, null|\WP_Post $postBefore): void
    {
        $newStatus = $post->post_status;
        $oldStatus = $postBefore ? $postBefore->post_status : 'new';

        if ($newStatus === 'publish') {
            // Post is being published or re-saved while published.
            // shouldIndex() now reads fully committed meta values.
            if (Indexer::shouldIndex($post)) {
                Indexer::index($post);
            } else {
                // Post is published but shouldIndex returned false (e.g. user
                // ticked "Exclude from index") — ensure it is removed.
                Indexer::deindex($post->ID);
            }
            return;
        }

        // Transitioning away from published (unpublishing) → remove from index.
        if ($oldStatus === 'publish') {
            Indexer::deindex($post->ID);
        }
    }

    /**
     * Triggered when a post is trashed or permanently deleted.
     *
     * @param int $postId WordPress post ID.
     */
    public function onPostDeindexed(int $postId): void
    {
        Indexer::deindex($postId);
    }
}
