<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Admin;

use Brain\Monkey\Functions;
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

    /**
     * When Typesense is not configured (OPTION_REMOTE is empty), the server
     * version cannot be determined, so sanitizePinnedResultsEnabled must always
     * return 0 regardless of the submitted value.
     */
    public function test_sanitize_pinned_results_enabled_returns_zero_when_server_has_no_version(): void
    {
        // Stub get_option to return '' so ServerCapabilities::getServerVersion()
        // exits early and supportsCurationSets() returns false.
        Functions\when('get_option')->justReturn('');

        self::assertSame(0, $this->settings->sanitizePinnedResultsEnabled(1));
        self::assertSame(0, $this->settings->sanitizePinnedResultsEnabled(0));
    }

    /**
     * When Typesense is not configured (OPTION_REMOTE is empty), the server
     * version cannot be determined, so sanitizeSynonymsEnabled must always
     * return 0 regardless of the submitted value.
     */
    public function test_sanitize_synonyms_enabled_returns_zero_when_server_has_no_version(): void
    {
        Functions\when('get_option')->justReturn('');

        self::assertSame(0, $this->settings->sanitizeSynonymsEnabled(1));
        self::assertSame(0, $this->settings->sanitizeSynonymsEnabled(0));
    }

    /**
     * Same as above, for sanitizeStemmingEnabled.
     */
    public function test_sanitize_stemming_enabled_returns_zero_when_server_has_no_version(): void
    {
        Functions\when('get_option')->justReturn('');

        self::assertSame(0, $this->settings->sanitizeStemmingEnabled(1));
        self::assertSame(0, $this->settings->sanitizeStemmingEnabled(0));
    }

    public function test_sanitize_vector_enabled_returns_zero_when_server_has_no_version(): void
    {
        Functions\when('get_option')->justReturn('');

        self::assertSame(0, $this->settings->sanitizeVectorEnabled(1));
        self::assertSame(0, $this->settings->sanitizeVectorEnabled(0));
    }

    public function test_sanitize_vector_model_preserves_previous_model_and_reports_error_on_invalid_input(): void
    {
        Functions\when('get_option')->justReturn('ts/multilingual-e5-small');
        $recordedError = null;
        Functions\when('add_settings_error')->alias(static function (string $setting, string $code, string $message) use (&$recordedError): void {
            $recordedError = [$setting, $code, $message];
        });

        self::assertSame('ts/multilingual-e5-small', $this->settings->sanitizeVectorModel('invalid model!'));
        self::assertNotNull($recordedError);
        self::assertSame(Settings::OPTION_VECTOR_MODEL, $recordedError[0]);
    }

    public function test_sanitize_vector_strategy_and_profile_and_custom_params(): void
    {
        $options = [
            Settings::OPTION_VECTOR_STRATEGY        => 'rescue',
            Settings::OPTION_VECTOR_RANKING_PROFILE => 'strict',
        ];
        Functions\when('get_option')->alias(static function (string $key, mixed $default = false) use (&$options): mixed {
            return $options[$key] ?? $default;
        });

        self::assertSame('parallel', $this->settings->sanitizeVectorStrategy('parallel'));
        self::assertSame('blended', $this->settings->sanitizeVectorStrategy('unknown'));
        self::assertSame('rescue', $this->settings->sanitizeVectorStrategy(null));

        self::assertSame('strict', $this->settings->sanitizeVectorRankingProfile('strict'));
        self::assertSame('balanced', $this->settings->sanitizeVectorRankingProfile('unknown'));
        self::assertSame('strict', $this->settings->sanitizeVectorRankingProfile(null));

        $options[Settings::OPTION_VECTOR_RANKING_PROFILE] = 'balanced';
        $custom = $this->settings->sanitizeVectorCustomParams(['alpha' => '0.45', 'k' => '80']);
        self::assertSame(0.45, $custom['alpha']);
        self::assertSame(80, $custom['k']);
    }

    public function test_sanitize_vector_custom_params_keeps_active_preset_when_keys_are_absent(): void
    {
        $options = [
            Settings::OPTION_VECTOR_MODEL           => 'ts/paraphrase-multilingual-mpnet-base-v2',
            Settings::OPTION_VECTOR_RANKING_PROFILE => 'strict',
        ];
        Functions\when('get_option')->alias(static function (string $key, mixed $default = false) use (&$options): mixed {
            return $options[$key] ?? $default;
        });

        try {
            $_POST[Settings::OPTION_VECTOR_RANKING_PROFILE] = 'strict';
            $strictMpnet = $this->settings->sanitizeVectorCustomParams(null);
            self::assertSame(0.40, $strictMpnet['alpha']);
            self::assertSame(0.62, $strictMpnet['distance_threshold']);
            self::assertSame(60, $strictMpnet['k']);
            self::assertSame(0.56, $strictMpnet['spotlight_distance_threshold']);
            self::assertSame(0, $strictMpnet['rescue_max_keyword_hits']);

            $_POST[Settings::OPTION_VECTOR_MODEL] = 'ts/multilingual-e5-small';
            $strictE5 = $this->settings->sanitizeVectorCustomParams(null);
            self::assertSame(0.40, $strictE5['alpha']);
            self::assertSame(0.20, $strictE5['distance_threshold']);
            self::assertSame(0.18, $strictE5['spotlight_distance_threshold']);

            $_POST[Settings::OPTION_VECTOR_RANKING_PROFILE] = 'discovery';
            unset($_POST[Settings::OPTION_VECTOR_MODEL]);
            $discovery = $this->settings->sanitizeVectorCustomParams(null);
            self::assertSame(0.70, $discovery['alpha']);
            self::assertSame(0.82, $discovery['distance_threshold']);
            self::assertSame(150, $discovery['k']);
            self::assertSame(0.75, $discovery['spotlight_distance_threshold']);
        } finally {
            unset(
                $_POST[Settings::OPTION_VECTOR_RANKING_PROFILE],
                $_POST[Settings::OPTION_VECTOR_MODEL]
            );
        }
    }

    public function test_sanitize_vector_custom_params_preserves_absent_strategy_keys_for_custom_profile(): void
    {
        $options = [
            Settings::OPTION_VECTOR_MODEL           => 'ts/paraphrase-multilingual-mpnet-base-v2',
            Settings::OPTION_VECTOR_RANKING_PROFILE => 'custom',
            Settings::OPTION_VECTOR_CUSTOM_PARAMS   => [
                'alpha'                        => 0.40,
                'distance_threshold'           => 0.62,
                'k'                            => 60,
                'rerank_hybrid_matches'        => false,
                'spotlight_max_hits'           => 2,
                'spotlight_distance_threshold' => 0.56,
                'spotlight_min_words'          => 2,
                'spotlight_dedupe'             => false,
                'rescue_max_keyword_hits'      => 0,
            ],
        ];
        Functions\when('get_option')->alias(static function (string $key, mixed $default = false) use (&$options): mixed {
            return $options[$key] ?? $default;
        });

        try {
            $_POST[Settings::OPTION_VECTOR_RANKING_PROFILE] = 'custom';
            $saved = $this->settings->sanitizeVectorCustomParams([
                'alpha'                 => '0.58',
                'distance_threshold'    => '0.75',
                'k'                     => '100',
                'rerank_hybrid_matches' => '0',
            ]);

            self::assertSame(0.58, $saved['alpha']);
            self::assertSame(0.75, $saved['distance_threshold']);
            self::assertSame(100, $saved['k']);
            self::assertFalse($saved['rerank_hybrid_matches']);
            self::assertSame(2, $saved['spotlight_max_hits']);
            self::assertSame(0.56, $saved['spotlight_distance_threshold']);
            self::assertSame(2, $saved['spotlight_min_words']);
            self::assertFalse($saved['spotlight_dedupe']);
            self::assertSame(0, $saved['rescue_max_keyword_hits']);
        } finally {
            unset($_POST[Settings::OPTION_VECTOR_RANKING_PROFILE]);
        }
    }

    public function test_sanitize_admin_key_keeps_saved_value_when_field_is_left_blank(): void
    {
        Functions\when('get_option')->justReturn('previously-saved-key');

        self::assertSame('previously-saved-key', $this->settings->sanitizeAdminKey(''));
        self::assertSame('previously-saved-key', $this->settings->sanitizeAdminKey('   '));
    }

    public function test_sanitize_admin_key_accepts_a_new_value(): void
    {
        Functions\when('get_option')->justReturn('previously-saved-key');

        self::assertSame('new-key', $this->settings->sanitizeAdminKey('new-key'));
    }

    public function test_sanitize_admin_key_clears_only_with_explicit_clear_flag(): void
    {
        Functions\when('get_option')->justReturn('previously-saved-key');

        $_POST[Settings::OPTION_ADMIN_KEY . '_clear'] = '1';
        try {
            self::assertSame('', $this->settings->sanitizeAdminKey(''));
        } finally {
            unset($_POST[Settings::OPTION_ADMIN_KEY . '_clear']);
        }
    }

    public function test_get_default_query_by_weights_returns_one_for_all_fields(): void
    {
        $defaults = Settings::getDefaultQueryByWeights();

        self::assertSame(['title', 'excerpt', 'content', 'type_name', 'extra_terms'], array_keys($defaults));
        self::assertSame([1, 1, 1, 1, 1], array_values($defaults));
    }

    public function test_get_search_weight_fields_returns_expected_keys(): void
    {
        $fields = Settings::getSearchWeightFields();

        self::assertSame(['title', 'excerpt', 'content', 'type_name', 'extra_terms'], array_keys($fields));
        self::assertContainsOnly('string', $fields);
    }
}
