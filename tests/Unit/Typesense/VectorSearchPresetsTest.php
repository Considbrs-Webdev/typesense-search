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
