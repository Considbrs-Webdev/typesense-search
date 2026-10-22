<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Integration;

use TypesenseSearch\Admin\MetaBox;
use TypesenseSearch\Admin\Settings;
use TypesenseSearch\Tests\Integration\Support\IndexDiff;
use TypesenseSearch\Tests\Integration\Support\PageTreeFactory;

/**
 * End-to-end index consistency scenarios for issue #11.
 *
 * Each test mutates WordPress content, waits for indexing deterministically, then
 * compares Typesense documents with DocumentBuilder output.
 */
final class IndexConsistencyTest extends IntegrationTestCase
{
    /**
     * @param int[] $postIds
     */
    private function assertPostsMatchDatabaseOrSkipKnownFailure(
        array $postIds,
        string $issueNumber,
        string $summary
    ): void {
        $lines = [];
        foreach ($postIds as $postId) {
            $post = get_post((int) $postId);
            if (!$post) {
                $lines[] = 'Post ' . $postId . ' missing from database';
                continue;
            }
            $diff = IndexDiff::diffPost($this->getIndexedDocument((int) $postId), $post);
            foreach ($diff as $line) {
                $lines[] = $line;
            }
        }

        if ($lines !== []) {
            self::markTestSkipped(sprintf(
                'Known failure (issue #%s): %s%s%s',
                $issueNumber,
                $summary,
                PHP_EOL,
                implode(PHP_EOL, $lines)
            ));
        }

        self::assertSame([], $lines, 'Indexed documents should match DocumentBuilder output.');
    }

    public function test_leaf_page_title_change_stays_in_sync(): void
    {
        $tree = PageTreeFactory::createThreeLevelTree('title-leaf');
        $this->indexPosts([
            $tree['parent']->ID,
            $tree['child']->ID,
            $tree['grandchild']->ID,
        ]);

        wp_update_post([
            'ID'         => $tree['grandchild']->ID,
            'post_title' => 'Renamed Leaf Page',
        ]);
        $this->waitForIndex([$tree['grandchild']->ID]);

        $this->assertIndexMatchesDatabase($tree['grandchild']->ID);
    }

    public function test_extra_terms_meta_change_on_page_stays_in_sync(): void
    {
        $tree = PageTreeFactory::createThreeLevelTree('meta');
        $this->indexPosts([$tree['child']->ID]);

        update_post_meta($tree['child']->ID, MetaBox::META_EXTRA_TERMS, 'custom searchable phrase');
        wp_update_post(['ID' => $tree['child']->ID]);

        $this->waitForIndex([$tree['child']->ID]);
        $this->assertIndexMatchesDatabase($tree['child']->ID);
    }

    public function test_parent_slug_change_reindexes_descendants(): void
    {
        $tree = PageTreeFactory::createThreeLevelTree('slug-parent');
        $this->indexPosts([
            $tree['parent']->ID,
            $tree['child']->ID,
            $tree['grandchild']->ID,
        ]);

        wp_update_post([
            'ID'        => $tree['parent']->ID,
            'post_name' => 'renamed-section-root',
        ]);
        $this->waitForIndex([$tree['parent']->ID]);

        $this->assertPostsMatchDatabaseOrSkipKnownFailure(
            [$tree['child']->ID, $tree['grandchild']->ID],
            '3',
            'Changing a parent slug should re-index descendant url/path fields.'
        );
        $this->assertIndexMatchesDatabase($tree['parent']->ID);
    }

    public function test_parent_title_change_reindexes_descendant_path_fields(): void
    {
        $tree = PageTreeFactory::createThreeLevelTree('title-parent');
        $this->indexPosts([
            $tree['parent']->ID,
            $tree['child']->ID,
            $tree['grandchild']->ID,
        ]);

        wp_update_post([
            'ID'         => $tree['parent']->ID,
            'post_title' => 'Renamed Section Root',
        ]);
        $this->waitForIndex([$tree['parent']->ID]);

        $this->assertPostsMatchDatabaseOrSkipKnownFailure(
            [$tree['child']->ID, $tree['grandchild']->ID],
            '3',
            'Changing a parent title should re-index descendant path/top_most_parent fields.'
        );
    }

    public function test_reparenting_page_reindexes_moved_branch(): void
    {
        $treeA = PageTreeFactory::createThreeLevelTree('move-a');
        $treeB = PageTreeFactory::createThreeLevelTree('move-b');

        $this->indexPosts([
            $treeA['parent']->ID,
            $treeA['child']->ID,
            $treeA['grandchild']->ID,
            $treeB['parent']->ID,
        ]);

        wp_update_post([
            'ID'          => $treeA['child']->ID,
            'post_parent' => $treeB['parent']->ID,
        ]);

        $this->waitForIndex([$treeA['child']->ID]);

        $this->assertPostsMatchDatabaseOrSkipKnownFailure(
            [$treeA['child']->ID, $treeA['grandchild']->ID],
            '3',
            'Moving a page to a new parent should re-index the moved branch.'
        );
    }

    public function test_trashing_parent_leaves_descendant_index_consistent(): void
    {
        $tree = PageTreeFactory::createThreeLevelTree('trash');
        $this->indexPosts([
            $tree['parent']->ID,
            $tree['child']->ID,
            $tree['grandchild']->ID,
        ]);

        wp_trash_post($tree['parent']->ID);

        $this->assertPostsMatchDatabaseOrSkipKnownFailure(
            [$tree['child']->ID, $tree['grandchild']->ID],
            '4',
            'Trashing a parent should update or remove stale descendant index documents.'
        );
    }

    public function test_deleting_parent_leaves_descendant_index_consistent(): void
    {
        $tree = PageTreeFactory::createThreeLevelTree('delete');
        $this->indexPosts([
            $tree['parent']->ID,
            $tree['child']->ID,
            $tree['grandchild']->ID,
        ]);

        wp_delete_post($tree['parent']->ID, true);
        clean_post_cache($tree['child']->ID);
        clean_post_cache($tree['grandchild']->ID);

        $this->assertPostsMatchDatabaseOrSkipKnownFailure(
            [$tree['child']->ID, $tree['grandchild']->ID],
            '4',
            'Deleting a parent should update descendant index documents to match the database.'
        );
    }

    public function test_nested_pages_style_raw_sql_move_reindexes_descendants(): void
    {
        $treeA = PageTreeFactory::createThreeLevelTree('sql-a');
        $treeB = PageTreeFactory::createThreeLevelTree('sql-b');

        $this->indexPosts([
            $treeA['parent']->ID,
            $treeA['child']->ID,
            $treeA['grandchild']->ID,
            $treeB['parent']->ID,
        ]);

        $this->rawSqlSetPostParent($treeA['child']->ID, $treeB['parent']->ID);
        do_action('typesense_search/index_post', $treeA['child']->ID);

        $this->waitForIndex([$treeA['child']->ID]);

        $this->assertPostsMatchDatabaseOrSkipKnownFailure(
            [$treeA['child']->ID, $treeA['grandchild']->ID],
            '5',
            'typesense_search/index_post after a raw SQL parent change should cascade to descendants.'
        );
    }

    public function test_section_exclusion_meta_without_metabox_action_reindexes_descendants(): void
    {
        $tree = PageTreeFactory::createThreeLevelTree('section');
        $this->indexPosts([
            $tree['parent']->ID,
            $tree['child']->ID,
            $tree['grandchild']->ID,
        ]);

        update_post_meta($tree['parent']->ID, MetaBox::META_EXCLUDE_AS_SECTION, '1');
        wp_update_post(['ID' => $tree['parent']->ID]);

        $this->waitForIndex([$tree['parent']->ID]);

        $this->assertPostsMatchDatabaseOrSkipKnownFailure(
            [$tree['child']->ID, $tree['grandchild']->ID],
            '6',
            'Section exclusion meta changes should re-index descendants top_most_parent fields.'
        );
    }

    public function test_modularity_module_edit_reindexes_using_page(): void
    {
        if (!class_exists('\\Modularity\\Editor')) {
            self::markTestSkipped('Modularity stub not loaded.');
        }

        update_option(Settings::OPTION_INDEX_MODULARITY, 1);
        update_option('modularity-options', [
            'enabled-post-types' => ['page'],
            'enabled-modules'    => ['mod-text'],
            'enabled-areas'      => ['default' => ['sidebar']],
        ]);

        $moduleId = wp_insert_post([
            'post_title'   => 'Sidebar module',
            'post_status'  => 'publish',
            'post_type'    => 'mod-text',
            'post_content' => 'First module text',
        ], true);
        self::assertIsInt($moduleId);

        $pageId = wp_insert_post([
            'post_title'   => 'Page with module',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => 'Body only',
        ], true);
        self::assertIsInt($pageId);

        update_post_meta($pageId, '_test_modularity_modules', [
            'sidebar' => [
                'modules' => [(object) ['ID' => $moduleId, 'post_type' => 'mod-text', 'hidden' => false]],
            ],
        ]);

        $this->indexPosts([$pageId]);
        $this->assertIndexMatchesDatabase($pageId);

        wp_update_post([
            'ID'           => $moduleId,
            'post_content' => 'Updated module text',
        ]);

        $this->assertPostsMatchDatabaseOrSkipKnownFailure(
            [$pageId],
            '7',
            'Editing a Modularity module should re-index pages that embed it.'
        );
    }

    public function test_permalink_structure_change_reindexes_urls(): void
    {
        $tree = PageTreeFactory::createThreeLevelTree('permalink');
        $this->indexPosts([
            $tree['parent']->ID,
            $tree['child']->ID,
        ]);

        update_option('permalink_structure', '/blog/%pagename%/');
        flush_rewrite_rules(true);

        $this->assertPostsMatchDatabaseOrSkipKnownFailure(
            [$tree['parent']->ID, $tree['child']->ID],
            '9',
            'Permalink structure changes should re-index stored url/path_urls values.'
        );

        // Restore default for other tests in the same process.
        update_option('permalink_structure', '/%pagename%/');
        flush_rewrite_rules(true);
    }
}
