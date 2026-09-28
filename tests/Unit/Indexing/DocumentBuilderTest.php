<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Indexing;

use Brain\Monkey\Functions;
use TypesenseSearch\Admin\MetaBox;
use TypesenseSearch\Helper\ExcerptHelper;
use TypesenseSearch\Indexing\DocumentBuilder;
use TypesenseSearch\Tests\TestCase;

class DocumentBuilderTest extends TestCase
{
    public function test_build_returns_the_expected_document_shape_and_applies_public_filters(): void
    {
        $post = new \WP_Post(
            ID: 123,
            post_title: 'A searchable page',
            post_content: '<p>Original content</p>',
            post_excerpt: 'Excerpt text',
            post_type: 'page',
            post_date_gmt: '2024-01-01 00:00:00',
            post_modified_gmt: '2024-01-02 03:04:05'
        );

        Functions\expect('has_post_thumbnail')->once()->with(123)->andReturn(true);
        Functions\expect('get_the_post_thumbnail_url')->once()->with(123, 'medium')->andReturn('https://example.test/thumb.jpg');
        Functions\expect('get_the_excerpt')->once()->with($post)->andReturn('Excerpt text');
        Functions\expect('get_permalink')->once()->with($post)->andReturn('https://example.test/page');
        Functions\expect('get_post_type_object')->once()->with('page')->andReturn((object) ['label' => 'Pages']);
        Functions\expect('get_option')->once()->with('date_format')->andReturn('Y-m-d');
        Functions\expect('date_i18n')->once()->with('Y-m-d', strtotime('2024-01-02 03:04:05'))->andReturn('2024-01-02');
        Functions\expect('get_post_meta')->once()->with(123, MetaBox::META_EXTRA_TERMS, true)->andReturn('municipio search');

        Functions\when('apply_filters')->alias(static function (string $hook, mixed $value, mixed ...$args) {
            if ($hook === 'the_content') {
                return '<p>Filtered content</p>';
            }

            if ($hook === ExcerptHelper::FILTER_LENGTH) {
                return $value;
            }

            if ($hook === DocumentBuilder::FILTER_BUILD) {
                $value['global_filter'] = true;

                return $value;
            }

            if ($hook === sprintf(DocumentBuilder::FILTER_BUILD_POST_TYPE, 'page')) {
                $value['page_filter'] = $args[0]->ID;

                return $value;
            }

            return $value;
        });

        $document = DocumentBuilder::build($post)->toArray();

        self::assertSame('123', $document['id']);
        self::assertSame('A searchable page', $document['title']);
        self::assertSame('Filtered content', $document['content']);
        self::assertSame('Excerpt text', $document['excerpt']);
        self::assertSame('https://example.test/page', $document['url']);
        self::assertSame('page', $document['type']);
        self::assertSame('Pages', $document['type_name']);
        self::assertSame(strtotime('2024-01-02 03:04:05'), $document['date']);
        self::assertSame('2024-01-02', $document['post_date_formatted']);
        self::assertSame('https://example.test/thumb.jpg', $document['thumbnail']);
        self::assertSame('municipio search', $document['extra_terms']);
        self::assertTrue($document['global_filter']);
        self::assertSame(123, $document['page_filter']);
    }

    public function test_build_indexes_plain_text_with_entities_decoded_after_tags_are_stripped(): void
    {
        $post = new \WP_Post(
            ID: 7,
            post_title: 'Bananveckan &#8211; Banankampen',
            post_content: '',
            post_type: 'page',
            post_date_gmt: '2024-01-01 00:00:00'
        );

        Functions\when('has_post_thumbnail')->justReturn(false);
        Functions\when('get_the_excerpt')->justReturn('Fika &amp; lek');
        Functions\when('get_permalink')->justReturn('https://example.test/banan');
        Functions\when('get_post_type_object')->justReturn((object) ['label' => 'Pages']);
        Functions\when('get_option')->justReturn('Y-m-d');
        Functions\when('date_i18n')->justReturn('2024-01-01');
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('apply_filters')->alias(static function (string $hook, mixed $value) {
            // An escaped tag is text written by the editor, not markup to strip.
            return $hook === 'the_content' ? '<p>Skriv &lt;b&gt; &#8220;här&#8221;&nbsp;nu</p>' : $value;
        });

        $document = DocumentBuilder::build($post)->toArray();

        self::assertSame('Bananveckan – Banankampen', $document['title']);
        self::assertSame("Skriv <b> “här”\u{00A0}nu", $document['content']);
        self::assertSame('Fika & lek', $document['excerpt']);
    }

    public function test_excerpt_is_measured_and_cut_after_decoding(): void
    {
        Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value) => $hook === ExcerptHelper::FILTER_LENGTH ? 12 : $value);

        // Nine characters once decoded, so it fits; raw it is 21 and would be cut mid-entity.
        self::assertSame('A – B – C', ExcerptHelper::build('A &#8211; B &#8211; C'));
        self::assertSame('Aaa – Bbb – [...]', ExcerptHelper::build('Aaa &#8211; Bbb &#8211; Ccc'));
    }
}
