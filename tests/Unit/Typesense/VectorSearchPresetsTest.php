<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Typesense;

use TypesenseSearch\Tests\TestCase;
use TypesenseSearch\Typesense\VectorSearchPresets;

class VectorSearchPresetsTest extends TestCase
{
    public function test_strategies_and_profiles_expose_labels_and_descriptions(): void
    {
        $strategies = VectorSearchPresets::strategies();
        self::assertSame(['blended', 'parallel', 'rescue'], array_keys($strategies));
        foreach ($strategies as $entry) {
            self::assertNotEmpty($entry['label']);
            self::assertNotEmpty($entry['description']);
        }

        $profiles = VectorSearchPresets::rankingProfiles();
        self::assertSame(['balanced', 'strict', 'discovery', 'custom'], array_keys($profiles));
        foreach ($profiles as $entry) {
            self::assertNotEmpty($entry['label']);
            self::assertNotEmpty($entry['description']);
        }

        $subsiteProfiles = VectorSearchPresets::subsiteProfiles();
        self::assertSame(['standard', 'tightened', 'keyword_only'], array_keys($subsiteProfiles));
        foreach ($subsiteProfiles as $entry) {
            self::assertNotEmpty($entry['short_label']);
            self::assertNotEmpty($entry['label']);
            self::assertNotEmpty($entry['description']);
        }
    }

    public function test_sanitizes_strategy_profile_and_subsite_profile(): void
    {
        self::assertSame('parallel', VectorSearchPresets::sanitizeStrategy('parallel'));
        self::assertSame('rescue', VectorSearchPresets::sanitizeStrategy('rescue'));
        self::assertSame('blended', VectorSearchPresets::sanitizeStrategy('invalid'));
        self::assertSame('blended', VectorSearchPresets::sanitizeStrategy(null));

        self::assertSame('strict', VectorSearchPresets::sanitizeProfile('strict'));
        self::assertSame('custom', VectorSearchPresets::sanitizeProfile('custom'));
        self::assertSame('balanced', VectorSearchPresets::sanitizeProfile('unknown'));

        self::assertSame('tightened', VectorSearchPresets::sanitizeSubsiteProfile('tightened'));
        self::assertSame('keyword_only', VectorSearchPresets::sanitizeSubsiteProfile('keyword_only'));
        self::assertSame('standard', VectorSearchPresets::sanitizeSubsiteProfile('bogus'));
    }

    public function test_sanitize_custom_params_clamps_bounds_and_handles_invalid_types(): void
    {
        $clamped = VectorSearchPresets::sanitizeCustomParams([
            'alpha'                        => '5.0',
            'distance_threshold'           => '0.01',
            'k'                            => '9999',
            'rerank_hybrid_matches'        => '0',
            'spotlight_max_hits'           => '99',
            'spotlight_distance_threshold' => '4.0',
            'spotlight_min_words'          => '0',
            'spotlight_dedupe'             => 0,
            'rescue_max_keyword_hits'      => '-4',
        ]);

        self::assertSame(0.95, $clamped['alpha']);
        self::assertSame(0.10, $clamped['distance_threshold']);
        self::assertSame(250, $clamped['k']);
        self::assertFalse($clamped['rerank_hybrid_matches']);
        self::assertSame(5, $clamped['spotlight_max_hits']);
        self::assertSame(1.00, $clamped['spotlight_distance_threshold']);
        self::assertSame(1, $clamped['spotlight_min_words']);
        self::assertFalse($clamped['spotlight_dedupe']);
        self::assertSame(0, $clamped['rescue_max_keyword_hits']);

        self::assertSame(
            VectorSearchPresets::presetParams(VectorSearchPresets::PROFILE_BALANCED),
            VectorSearchPresets::sanitizeCustomParams('not-an-array')
        );

        self::assertSame(
            VectorSearchPresets::presetParams(VectorSearchPresets::PROFILE_STRICT, 'ts/multilingual-e5-small'),
            VectorSearchPresets::sanitizeCustomParams(
                null,
                'ts/multilingual-e5-small',
                VectorSearchPresets::PROFILE_STRICT
            )
        );
    }

    public function test_every_preset_value_satisfies_custom_input_constraints_and_hidden_inputs_are_disabled(): void
    {
        $options = [
            \TypesenseSearch\Admin\Settings::OPTION_VECTOR_ENABLED         => 1,
            \TypesenseSearch\Admin\Settings::OPTION_VECTOR_RANKING_PROFILE => VectorSearchPresets::PROFILE_BALANCED,
            \TypesenseSearch\Admin\Settings::OPTION_VECTOR_STRATEGY        => VectorSearchPresets::STRATEGY_BLENDED,
        ];

        \Brain\Monkey\Functions\when('get_option')->alias(
            static function (string $key, mixed $default = false) use (&$options): mixed {
                return $options[$key] ?? $default;
            }
        );
        foreach (['esc_html', 'esc_attr', 'esc_url'] as $fn) {
            \Brain\Monkey\Functions\when($fn)->alias(
                static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES)
            );
        }
        \Brain\Monkey\Functions\when('esc_html_e')->alias(
            static function (mixed $v): void {
                echo htmlspecialchars((string) $v, ENT_QUOTES);
            }
        );
        \Brain\Monkey\Functions\when('esc_attr_e')->alias(
            static function (mixed $v): void {
                echo htmlspecialchars((string) $v, ENT_QUOTES);
            }
        );
        \Brain\Monkey\Functions\when('esc_html__')->returnArg(1);
        \Brain\Monkey\Functions\when('selected')->alias(static function (mixed $a, mixed $b): string {
            if ((string) $a === (string) $b) {
                echo 'selected="selected"';
                return 'selected="selected"';
            }
            return '';
        });
        \Brain\Monkey\Functions\when('checked')->alias(static function (mixed $a, mixed $b = true): string {
            if ((string) $a === (string) $b) {
                echo 'checked="checked"';
                return 'checked="checked"';
            }
            return '';
        });

        $renderView = static function () use (&$options): \DOMXPath {
            $supportsPinnedResults = false;
            $supportsSynonyms      = false;
            $supportsStemming      = false;
            $supportsAutoEmbedding = true;
            $stemmingLocale        = 'sv';
            $networkMode           = false;

            ob_start();
            try {
                include dirname(__DIR__, 3) . '/views/admin/settings-tabs/advanced/search-result-behavior.php';
                $html = (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }

            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
            return new \DOMXPath($dom);
        };

        // 1. Preset profile (balanced): panel is hidden and every custom-param input is disabled.
        $xpath = $renderView();
        $panel = $xpath->query('//*[@id="ts-vector-custom-params"]')->item(0);
        self::assertInstanceOf(\DOMElement::class, $panel);
        self::assertTrue($panel->hasAttribute('hidden'));

        $panelInputs = $xpath->query('//*[@id="ts-vector-custom-params"]//input');
        self::assertGreaterThan(0, $panelInputs->length);
        foreach ($panelInputs as $input) {
            self::assertInstanceOf(\DOMElement::class, $input);
            self::assertTrue(
                $input->hasAttribute('disabled'),
                sprintf('Expected hidden custom-param input "%s" to be disabled', $input->getAttribute('name'))
            );
        }

        // 2. Custom profile + blended strategy: panel visible, base inputs enabled, hidden strategy-only inputs disabled.
        $options[\TypesenseSearch\Admin\Settings::OPTION_VECTOR_RANKING_PROFILE] = VectorSearchPresets::PROFILE_CUSTOM;
        $xpath = $renderView();
        $panel = $xpath->query('//*[@id="ts-vector-custom-params"]')->item(0);
        self::assertInstanceOf(\DOMElement::class, $panel);
        self::assertFalse($panel->hasAttribute('hidden'));

        foreach (['ts-vector-alpha', 'ts-vector-distance-threshold', 'ts-vector-k', 'ts-vector-rerank'] as $enabledId) {
            $el = $xpath->query(sprintf('//*[@id="%s"]', $enabledId))->item(0);
            self::assertInstanceOf(\DOMElement::class, $el);
            self::assertFalse($el->hasAttribute('disabled'), sprintf('Expected #%s to be enabled', $enabledId));
        }
        foreach ([
            'ts-vector-spotlight-max-hits',
            'ts-vector-spotlight-threshold',
            'ts-vector-spotlight-min-words',
            'ts-vector-spotlight-dedupe',
            'ts-vector-rescue-max-hits',
        ] as $disabledId) {
            $el = $xpath->query(sprintf('//*[@id="%s"]', $disabledId))->item(0);
            self::assertInstanceOf(\DOMElement::class, $el);
            self::assertTrue($el->hasAttribute('disabled'), sprintf('Expected #%s to be disabled while hidden', $disabledId));
        }

        // 3. Every preset value across all profiles and models satisfies the rendered min/max/step attributes.
        $constraints  = [];
        $numberInputs = $xpath->query('//*[@id="ts-vector-custom-params"]//input[@type="number"]');
        foreach ($numberInputs as $input) {
            self::assertInstanceOf(\DOMElement::class, $input);
            if (preg_match('/\[([a-z_]+)\]$/', $input->getAttribute('name'), $nameMatch)) {
                $constraints[$nameMatch[1]] = [
                    'min'  => (float) $input->getAttribute('min'),
                    'max'  => (float) $input->getAttribute('max'),
                    'step' => $input->getAttribute('step'),
                ];
            }
        }

        self::assertSame(
            [
                'alpha',
                'distance_threshold',
                'k',
                'spotlight_max_hits',
                'spotlight_distance_threshold',
                'spotlight_min_words',
                'rescue_max_keyword_hits',
            ],
            array_keys($constraints)
        );

        $models = [
            'ts/paraphrase-multilingual-mpnet-base-v2',
            'ts/multilingual-e5-small',
            'ts/multilingual-e5-base',
            'ts/all-MiniLM-L12-v2',
        ];
        $profiles = [
            VectorSearchPresets::PROFILE_BALANCED,
            VectorSearchPresets::PROFILE_STRICT,
            VectorSearchPresets::PROFILE_DISCOVERY,
        ];

        foreach ($profiles as $profile) {
            foreach ($models as $model) {
                $params = VectorSearchPresets::presetParams($profile, $model);
                foreach ($constraints as $key => $rule) {
                    $val = (float) $params[$key];
                    self::assertGreaterThanOrEqual($rule['min'], $val);
                    self::assertLessThanOrEqual($rule['max'], $val);

                    if ($rule['step'] !== 'any') {
                        $step  = (float) $rule['step'];
                        $steps = ($val - $rule['min']) / $step;
                        self::assertEqualsWithDelta(
                            round($steps),
                            $steps,
                            1e-9,
                            sprintf(
                                '%s=%s (%s / %s) fails step=%s from min=%s',
                                $key,
                                (string) $params[$key],
                                $profile,
                                $model,
                                $rule['step'],
                                (string) $rule['min']
                            )
                        );
                    }
                }
            }
        }
    }

    public function test_resolve_params_calibrates_mpnet_vs_e5_and_disables_auxiliary_rerank_by_default(): void
    {
        $balancedMpnet = VectorSearchPresets::resolveParams('balanced', [], 'ts/paraphrase-multilingual-mpnet-base-v2');
        self::assertSame(0.58, $balancedMpnet['alpha']);
        self::assertSame(0.75, $balancedMpnet['distance_threshold']);
        self::assertFalse($balancedMpnet['rerank_hybrid_matches']);
        self::assertSame(0.68, $balancedMpnet['spotlight_distance_threshold']);
        self::assertSame(1, $balancedMpnet['spotlight_min_words']);
        self::assertSame(0.58, $balancedMpnet['quick_alpha']);
        self::assertSame(0.68, $balancedMpnet['quick_distance_threshold']);

        $balancedE5 = VectorSearchPresets::resolveParams('balanced', [], 'ts/multilingual-e5-small');
        self::assertSame(0.58, $balancedE5['alpha']);
        self::assertSame(0.25, $balancedE5['distance_threshold']);
        self::assertSame(0.22, $balancedE5['spotlight_distance_threshold']);
        self::assertSame(0.22, $balancedE5['quick_distance_threshold']);

        $strict = VectorSearchPresets::resolveParams('strict', ['alpha' => 0.90], 'ts/paraphrase-multilingual-mpnet-base-v2');
        self::assertSame(0.40, $strict['alpha']);
        self::assertSame(0.62, $strict['distance_threshold']);
        self::assertSame(60, $strict['k']);
        self::assertFalse($strict['rerank_hybrid_matches']);
        self::assertSame(0.40, $strict['quick_alpha']);
        self::assertSame(0.56, $strict['quick_distance_threshold']);
        self::assertSame(40, $strict['quick_k']);

        $custom = VectorSearchPresets::resolveParams('custom', [
            'alpha'                        => 0.65,
            'distance_threshold'           => 0.50,
            'k'                            => 20,
            'spotlight_distance_threshold' => 0.44,
        ]);
        self::assertSame(0.65, $custom['alpha']);
        self::assertSame(0.58, $custom['quick_alpha']);
        self::assertSame(0.44, $custom['quick_distance_threshold']);
        self::assertSame(20, $custom['quick_k']);
    }
}
