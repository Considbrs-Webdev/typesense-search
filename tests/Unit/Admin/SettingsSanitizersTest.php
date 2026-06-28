<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Admin;

use TypesenseSearch\Admin\Settings;
use TypesenseSearch\Tests\TestCase;

class SettingsSanitizersTest extends TestCase
{
    private Settings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = $this->instantiateWithoutConstructor(Settings::class);
    }

    public function test_sanitize_post_types_returns_clean_keys(): void
    {
        self::assertSame(
            ['post', 'page', 'badtype', 'custom-type'],
            $this->settings->sanitizePostTypes(['Post', 'page', 'Bad Type!', 'custom-type'])
        );
    }

    public function test_sanitize_post_types_rejects_non_arrays(): void
    {
        self::assertSame([], $this->settings->sanitizePostTypes('post'));
    }

    public function test_sanitize_query_by_weights_clamps_known_fields_and_defaults_missing_values(): void
    {
        self::assertSame(
            [
                'title'       => 5,
                'excerpt'     => 1,
                'content'     => 3,
                'type_name'   => 1,
                'extra_terms' => 2,
            ],
            $this->settings->sanitizeQueryByWeights([
                'title'       => 99,
                'excerpt'     => 0,
                'content'     => 3,
                'unknown'     => 4,
                'extra_terms' => -2,
            ])
        );
    }

    public function test_sanitize_quick_search_selectors_keeps_valid_rows_and_normalizes_mobile_behavior(): void
    {
        self::assertSame(
            [
                [
                    'selector'        => '.site-search',
                    'sibling'         => true,
                    'mobile_behavior' => 'overlay',
                ],
                [
                    'selector'        => '#header-search',
                    'sibling'         => false,
                    'mobile_behavior' => 'regular',
                ],
            ],
            $this->settings->sanitizeQuickSearchSelectors([
                ['selector' => ' .site-search ', 'sibling' => '1', 'mobile_behavior' => 'overlay'],
                ['selector' => '', 'sibling' => true],
                'invalid',
                ['selector' => '#header-search', 'mobile_behavior' => 'drawer'],
            ])
        );
    }

    public function test_sanitize_facets_keeps_valid_rows_and_defaults_invalid_display_modes(): void
    {
        self::assertSame(
            [
                [
                    'field'       => 'posttype',
                    'label'       => 'Content type',
                    'placeholder' => 'Choose',
                    'display_as'  => 'button_group',
                ],
                [
                    'field'       => 'category',
                    'label'       => '',
                    'placeholder' => '',
                    'display_as'  => 'dropdown',
                ],
            ],
            $this->settings->sanitizeFacets([
                [
                    'field'       => 'Post Type',
                    'label'       => ' Content type ',
                    'placeholder' => ' Choose ',
                    'display_as'  => 'button_group',
                ],
                ['field' => 'category', 'display_as' => 'checkboxes'],
                ['field' => '', 'label' => 'Empty'],
                'invalid',
            ])
        );
    }
}
