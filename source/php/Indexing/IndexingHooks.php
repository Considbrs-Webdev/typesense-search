<?php

namespace TypesenseSearch\Indexing;

use TypesenseSearch\Admin\MetaBox;
use TypesenseSearch\Indexing\Strategies\PdfIndexingStrategy;
use TypesenseSearch\Logger\ErrorLogLogger;
use TypesenseSearch\Logger\LoggerInterface;

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

    /** @var array<int, int[]> Descendant IDs of pages being deleted, keyed by page ID. */
    private array $descendantsOfDeleted = [];

    private LoggerInterface $logger;

    public function __construct(IndexingRegistry $registry, ?LoggerInterface $logger = null)
    {
        $this->registry = $registry;
        $this->logger = $logger ?? new ErrorLogLogger();

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

        // WordPress reparents a deleted page's children to its parent, which
        // changes their URL and path. Remember them beforehand, re-index after.
        add_action('before_delete_post', [$this, 'rememberDescendantsOfDeletedPage']);
        add_action('deleted_post', [$this, 'reindexDescendantsOfDeletedPage']);

        // Modularity module content is appended to the pages that display the
        // module, so editing or removing a module must re-index those pages.
        add_action('wp_after_insert_post', [$this, 'onModuleSaved'], 30, 2);
        add_action('deleted_post', [$this, 'onModuleDeleted'], 10, 2);

        // External API: allow any plugin/theme to trigger index or deindex.
        //   do_action('typesense_search/index_post', $post_id);
        //   do_action('typesense_search/deindex_post', $post_id);
        add_action('typesense_search/index_post', [$this, 'onExternalIndexPost']);
        add_action('typesense_search/deindex_post', [$this, 'onPostDeindexed']);

        // Fired by the admin metabox when a page's section-exclusion flag
        // changes. Descendant pages and PDFs derive their section from this
        // ancestor, so they need to be rebuilt too.
        add_action('typesense_search/section_exclusion_changed', [$this, 'onSectionExclusionChanged']);

        // The flag can also be changed outside the metabox (WP-CLI, imports,
        // direct update_post_meta), so react to the meta itself.
        add_action('added_post_meta', [$this, 'onSectionExclusionMetaAdded'], 10, 4);
        add_action('updated_post_meta', [$this, 'onSectionExclusionMetaChanged'], 10, 4);
        add_action('deleted_post_meta', [$this, 'onSectionExclusionMetaChanged'], 10, 4);
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
        if (!(new \TypesenseSearch\Services\SettingsRepository())->canUseTypesense()) {
            return;
        }
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

            // Cascade: descendants derive their URL, path and section from
            // this page's ancestors, so rebuild them when it moves or renames.
            if ($update && $postBefore && $this->ancestorFieldsChanged($post, $postBefore)) {
                $this->reindexDescendants($post->ID);
            }

            return;
        }

        // Transitioning away from published (unpublishing) → remove from index.
        if ($oldStatus === 'publish') {
            $strategy->deindex($post->ID);

            // Cascade: published descendants keep their post_parent when the
            // page is unpublished or trashed, but their ancestor-derived
            // fields (path, URL, section) must be rebuilt.
            if ($post->post_type === 'page') {
                $this->reindexDescendants($post->ID);
            }
        }
    }

    /**
     * Triggered by the `typesense_search/index_post` action.
     *
     * Lets external plugins and themes request (re-)indexing of a single post
     * without depending on internal classes:
     *
     *   do_action('typesense_search/index_post', $post_id);
     *
     * The post must be published. If no strategy supports the post type, or if
     * the strategy's shouldIndex() returns false, the call is a no-op (the
     * document is removed from the index in the latter case, matching the
     * behaviour of onAfterInsertPost).
     *
     * @param int $postId WordPress post ID.
     */
    public function onExternalIndexPost(int $postId): void
    {
        if (!(new \TypesenseSearch\Services\SettingsRepository())->canUseTypesense()) {
            return;
        }
        // Raw SQL writes (e.g. Nested Pages sort) bypass WordPress and leave
        // stale data in the object cache. Clear it before reading the post.
        clean_post_cache($postId);
        $post = get_post($postId);
        if (!$post || $post->post_status !== 'publish') {
            return;
        }

        $strategy = $this->registry->resolve($post);
        if ($strategy === null) {
            return;
        }

        if ($strategy->shouldIndex($post)) {
            $strategy->index($post);
        } else {
            $strategy->deindex($post->ID);
        }

        // Raw SQL moves (e.g. Nested Pages) can relocate a whole branch, so
        // descendants need their ancestor-derived fields rebuilt as well.
        if ($post->post_type === 'page') {
            $this->reindexDescendants($post->ID);
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
        if (!(new \TypesenseSearch\Services\SettingsRepository())->canUseTypesense()) {
            return;
        }
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

    /**
     * Re-index descendant pages and attached PDFs after a top page is included
     * or excluded as a search section.
     *
     * @param int $postId WordPress post ID.
     */
    public function onSectionExclusionChanged(int $postId): void
    {
        if (!(new \TypesenseSearch\Services\SettingsRepository())->canUseTypesense()) {
            return;
        }
        $post = get_post($postId);
        if (!$post || $post->post_type !== 'page' || !empty(get_post_ancestors($post))) {
            return;
        }

        $this->reindexDescendants($postId);
    }

    /**
     * Re-index pages displaying a Modularity module after it is saved or trashed.
     *
     * @param int       $postId Module post ID.
     * @param \WP_Post $post   Module post object.
     */
    public function onModuleSaved(int $postId, \WP_Post $post): void
    {
        $this->reindexPagesUsingModule($post);
    }

    /**
     * Re-index pages that displayed a Modularity module after it was deleted.
     *
     * @param int            $postId Module post ID.
     * @param \WP_Post|null $post   Deleted post object (WP 5.5+).
     */
    public function onModuleDeleted($postId, $post = null): void
    {
        if ($post instanceof \WP_Post) {
            $this->reindexPagesUsingModule($post);
        }
    }

    private function reindexPagesUsingModule(\WP_Post $module): void
    {
        if (
            strpos((string) $module->post_type, 'mod-') !== 0
            || !class_exists('\\Modularity\\Editor')
            || wp_is_post_revision($module->ID)
        ) {
            return;
        }

        $settings = new \TypesenseSearch\Services\SettingsRepository();
        if (!$settings->canUseTypesense() || !$settings->isIndexModularityEnabled()) {
            return;
        }

        foreach ($this->findPostsUsingModule($module->ID) as $id) {
            $this->reindexPost($id);
        }
    }

    /**
     * Fires the section-exclusion cascade when the flag meta is first added
     * with a truthy value (adding '0' is the default state, not a change).
     *
     * @param int    $metaId    Meta row ID.
     * @param int    $postId    Post ID.
     * @param string $metaKey   Meta key.
     * @param mixed  $metaValue Meta value.
     */
    public function onSectionExclusionMetaAdded($metaId, $postId, $metaKey, $metaValue): void
    {
        if ($metaKey === MetaBox::META_EXCLUDE_AS_SECTION && (string) $metaValue === '1') {
            do_action('typesense_search/section_exclusion_changed', (int) $postId);
        }
    }

    /**
     * Fires the section-exclusion cascade when the flag meta is updated or deleted.
     *
     * @param mixed  $metaId    Meta row ID(s).
     * @param int    $postId    Post ID.
     * @param string $metaKey   Meta key.
     * @param mixed  $metaValue Meta value.
     */
    public function onSectionExclusionMetaChanged($metaId, $postId, $metaKey, $metaValue): void
    {
        if ($metaKey === MetaBox::META_EXCLUDE_AS_SECTION) {
            do_action('typesense_search/section_exclusion_changed', (int) $postId);
        }
    }

    /**
     * Uses Modularity's own usage lookup, which also covers shortcode embeds.
     *
     * @return int[]
     */
    private function findPostsUsingModule(int $moduleId): array
    {
        if (!class_exists('\\Modularity\\Helper\\ModuleUsageById')) {
            $this->logger->warning(
                'TypesenseSearch: Modularity\\Helper\\ModuleUsageById not found; pages using module ' . $moduleId . ' were not re-indexed.'
            );

            return [];
        }

        $usage = \Modularity\Helper\ModuleUsageById::getModuleUsageById($moduleId);

        return array_values(array_unique(array_map(
            static fn ($row) => (int) $row->post_id,
            (array) $usage
        )));
    }

    /**
     * Capture the IDs of a page's published descendants before it is deleted.
     *
     * @param int $postId WordPress post ID.
     */
    public function rememberDescendantsOfDeletedPage(int $postId): void
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== 'page') {
            return;
        }

        $this->descendantsOfDeleted[$postId] = array_map('intval', wp_list_pluck(get_pages([
            'child_of'    => $postId,
            'post_status' => 'publish',
        ]), 'ID'));
    }

    /**
     * Re-index the descendants captured before the page was deleted, now that
     * WordPress has moved them to the deleted page's parent.
     *
     * @param int $postId WordPress post ID.
     */
    public function reindexDescendantsOfDeletedPage(int $postId): void
    {
        $ids = $this->descendantsOfDeleted[$postId] ?? [];
        unset($this->descendantsOfDeleted[$postId]);

        if (empty($ids) || !(new \TypesenseSearch\Services\SettingsRepository())->canUseTypesense()) {
            return;
        }

        foreach ($ids as $id) {
            $this->reindexPost($id);
        }
    }

    /**
     * Re-index every published descendant page (and its attached PDFs).
     *
     * @param int $postId Ancestor page ID.
     */
    private function reindexDescendants(int $postId): void
    {
        $descendants = get_pages([
            'child_of'    => $postId,
            'post_status' => 'publish',
            'sort_column' => 'menu_order',
        ]);

        foreach ($descendants as $descendant) {
            $this->reindexPost($descendant->ID);
        }
    }

    /**
     * Index or deindex one published post (and its PDFs) per its strategy.
     */
    private function reindexPost(int $postId): void
    {
        $post = get_post($postId);
        if (!$post || $post->post_status !== 'publish') {
            return;
        }

        $strategy = $this->registry->resolve($post);
        if ($strategy === null) {
            return;
        }

        if ($strategy->shouldIndex($post)) {
            $strategy->index($post);
        } else {
            $strategy->deindex($post->ID);
        }

        $this->cascadePdfReindex($post->ID);
    }

    /**
     * Whether a page change affects fields its descendants inherit
     * (permalink, ancestor URLs/titles).
     */
    private function ancestorFieldsChanged(\WP_Post $post, \WP_Post $before): bool
    {
        return $post->post_type === 'page'
            && ($post->post_name !== $before->post_name
                || (int) $post->post_parent !== (int) $before->post_parent
                || $post->post_title !== $before->post_title);
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
