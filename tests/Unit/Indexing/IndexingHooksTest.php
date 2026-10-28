<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Indexing;

use Brain\Monkey\Functions;
use Mockery;
use Mockery\MockInterface;
use TypesenseSearch\Indexing\Contracts\IndexingStrategyInterface;
use TypesenseSearch\Indexing\IndexingHooks;
use TypesenseSearch\Indexing\IndexingRegistry;
use TypesenseSearch\Tests\TestCase;

class IndexingHooksTestPost extends \WP_Post
{
    public string $post_status = 'publish';
    public string $post_name = '';
    public int $post_parent = 0;
}

class IndexingHooksTest extends TestCase
{
    /** @var array<int, IndexingHooksTestPost> */
    private array $posts = [];

    /** @var MockInterface&IndexingStrategyInterface */
    private $strategy;

    private IndexingHooks $hooks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->posts = [];
        $this->strategy = Mockery::mock(IndexingStrategyInterface::class);
        $this->strategy->shouldReceive('shouldIndex')->andReturn(true)->byDefault();

        $registry = Mockery::mock(IndexingRegistry::class);
        $registry->shouldReceive('resolve')->andReturn($this->strategy)->byDefault();
        $registry->shouldReceive('registerAllHooks')->byDefault();
        $registry->shouldReceive('get')->with('pdf')->andReturn(null)->byDefault();

        $this->hooks = new IndexingHooks($registry);

        Functions\when('get_post')->alias(fn (int $id) => $this->posts[$id] ?? null);
    }

    protected function tearDown(): void
    {
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
        parent::tearDown();
    }

    public function test_slug_change_reindexes_published_descendants(): void
    {
        $parent = $this->page(1, 'new-slug');
        $this->page(2, 'child', 1);
        $this->page(3, 'grandchild', 2);
        $this->stubDescendants(1, [2, 3]);

        $indexed = [];
        $this->strategy->shouldReceive('index')->andReturnUsing(function ($post) use (&$indexed) {
            $indexed[] = $post->ID;
            return true;
        });

        $this->hooks->onAfterInsertPost(1, $parent, true, $this->page(1, 'old-slug', 0, false));

        self::assertSame([1, 2, 3], $indexed);
    }

    public function test_parent_and_title_changes_reindex_descendants(): void
    {
        $this->page(2, 'child', 1);
        $this->stubDescendants(1, [2]);
        $this->strategy->shouldReceive('index')->times(4)->andReturn(true);

        $parent = $this->page(1, 'same');
        $this->hooks->onAfterInsertPost(1, $parent, true, $this->page(1, 'same', 5, false));

        $retitled = $this->page(1, 'same');
        $retitled->post_title = 'New title';
        $this->hooks->onAfterInsertPost(1, $retitled, true, $this->page(1, 'same', 0, false));
    }

    public function test_unrelated_update_does_not_reindex_descendants(): void
    {
        $parent = $this->page(1, 'same');
        $this->page(2, 'child', 1);
        Functions\expect('get_pages')->never();
        $this->strategy->shouldReceive('index')->once()->andReturn(true);

        $this->hooks->onAfterInsertPost(1, $parent, true, $this->page(1, 'same', 0, false));
    }

    public function test_new_page_does_not_reindex_descendants(): void
    {
        $parent = $this->page(1, 'slug');
        Functions\expect('get_pages')->never();
        $this->strategy->shouldReceive('index')->once()->andReturn(true);

        $this->hooks->onAfterInsertPost(1, $parent, false, null);
    }

    public function test_non_page_slug_change_does_not_reindex_descendants(): void
    {
        $post = $this->page(1, 'new');
        $post->post_type = 'post';
        $before = $this->page(1, 'old', 0, false);
        $before->post_type = 'post';
        Functions\expect('get_pages')->never();
        $this->strategy->shouldReceive('index')->once()->andReturn(true);

        $this->hooks->onAfterInsertPost(1, $post, true, $before);
    }

    public function test_descendant_that_should_not_be_indexed_is_deindexed(): void
    {
        $parent = $this->page(1, 'new');
        $this->page(2, 'child', 1);
        $this->stubDescendants(1, [2]);

        $this->strategy->shouldReceive('shouldIndex')->with(Mockery::on(fn ($p) => $p->ID === 2))->andReturn(false);
        $this->strategy->shouldReceive('index')->with(Mockery::on(fn ($p) => $p->ID === 1))->once()->andReturn(true);
        $this->strategy->shouldReceive('deindex')->with(2)->once()->andReturn(true);

        $this->hooks->onAfterInsertPost(1, $parent, true, $this->page(1, 'old', 0, false));
    }

    public function test_deleting_a_page_reindexes_its_former_descendants_after_deletion(): void
    {
        $this->page(1, 'parent');
        $this->page(2, 'child', 1);
        $this->page(3, 'grandchild', 2);
        $this->stubDescendants(1, [2, 3]);

        $indexed = [];
        $this->strategy->shouldReceive('index')->andReturnUsing(function ($post) use (&$indexed) {
            $indexed[] = $post->ID;
            return true;
        });

        $this->hooks->rememberDescendantsOfDeletedPage(1);
        self::assertSame([], $indexed, 'Nothing is re-indexed before the deletion completes.');

        // WordPress reparents the children, then fires deleted_post.
        $this->posts[2]->post_parent = 0;
        unset($this->posts[1]);
        $this->hooks->reindexDescendantsOfDeletedPage(1);

        self::assertSame([2, 3], $indexed);
    }

    public function test_deleting_a_non_page_reindexes_nothing(): void
    {
        $post = $this->page(1, 'a-post');
        $post->post_type = 'post';
        Functions\expect('get_pages')->never();
        $this->strategy->shouldReceive('index')->never();

        $this->hooks->rememberDescendantsOfDeletedPage(1);
        $this->hooks->reindexDescendantsOfDeletedPage(1);
    }

    public function test_unpublishing_a_page_reindexes_its_published_descendants(): void
    {
        $parent = $this->page(1, 'parent');
        $parent->post_status = 'trash';
        $this->page(2, 'child', 1);
        $this->stubDescendants(1, [2]);

        $this->strategy->shouldReceive('deindex')->with(1)->once()->andReturn(true);
        $this->strategy->shouldReceive('index')->with(Mockery::on(fn ($p) => $p->ID === 2))->once()->andReturn(true);

        $this->hooks->onAfterInsertPost(1, $parent, true, $this->page(1, 'parent', 0, false));
    }

    public function test_external_index_post_reindexes_descendants(): void
    {
        Functions\when('clean_post_cache')->justReturn();
        $this->page(1, 'moved');
        $this->page(2, 'child', 1);
        $this->stubDescendants(1, [2]);

        $indexed = [];
        $this->strategy->shouldReceive('index')->andReturnUsing(function ($post) use (&$indexed) {
            $indexed[] = $post->ID;
            return true;
        });

        $this->hooks->onExternalIndexPost(1);

        self::assertSame([1, 2], $indexed);
    }

    public function test_section_exclusion_meta_changes_fire_the_cascade_action(): void
    {
        Functions\expect('do_action')->with('typesense_search/section_exclusion_changed', 5)->twice();

        $this->hooks->onSectionExclusionMetaAdded(1, 5, '_typesense_exclude_as_section', '1');
        $this->hooks->onSectionExclusionMetaChanged(1, 5, '_typesense_exclude_as_section', '0');
        $this->hooks->onSectionExclusionMetaAdded(1, 5, '_typesense_exclude_as_section', '0');
        $this->hooks->onSectionExclusionMetaChanged(1, 5, 'other_key', '1');
    }

    public function test_saving_a_module_reindexes_pages_that_use_it(): void
    {
        eval('namespace Modularity; if (!class_exists(\'\\Modularity\\Editor\')) { class Editor {} }');
        eval('namespace Modularity\\Helper; if (!class_exists(\'\\Modularity\\Helper\\ModuleUsageById\')) { class ModuleUsageById { public static $rows = []; public static function getModuleUsageById(int $id) { return self::$rows; } } }');
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('get_option')->justReturn(['index_modularity' => '1']);
        $this->page(7, 'page-using-module');

        \Modularity\Helper\ModuleUsageById::$rows = [(object) ['post_id' => '7'], (object) ['post_id' => '7']];

        $indexed = [];
        $this->strategy->shouldReceive('index')->andReturnUsing(function ($post) use (&$indexed) {
            $indexed[] = $post->ID;
            return true;
        });

        $module = new IndexingHooksTestPost(99, 'Module', '', '', 'mod-text');
        $this->hooks->onModuleSaved(99, $module);

        self::assertSame([7], $indexed);
    }

    public function test_saving_a_regular_post_does_not_look_for_module_usage(): void
    {
        $this->strategy->shouldReceive('index')->never();
        $this->hooks->onModuleSaved(1, new IndexingHooksTestPost(1, 'T', '', '', 'page'));
    }

    private function page(int $id, string $slug, int $parent = 0, bool $store = true): IndexingHooksTestPost
    {
        $post = new IndexingHooksTestPost($id, 'Title', '', '', 'page');
        $post->post_name = $slug;
        $post->post_parent = $parent;

        if ($store) {
            $this->posts[$id] = $post;
        }

        return $post;
    }

    /** @param int[] $ids */
    private function stubDescendants(int $parentId, array $ids): void
    {
        Functions\when('get_pages')->alias(
            fn (array $args) => ($args['child_of'] ?? 0) === $parentId
                ? array_map(fn (int $id) => $this->posts[$id], $ids)
                : []
        );
        Functions\when('wp_list_pluck')->alias(
            fn (array $list, string $field) => array_map(fn ($item) => $item->$field, $list)
        );
    }
}
