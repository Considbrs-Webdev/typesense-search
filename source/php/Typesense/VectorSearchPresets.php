<?php

namespace TypesenseSearch\Typesense;

/**
 * Validated UX strategies, ranking preset profiles, custom parameter bounds,
 * and multisite subsite relevance profiles for embedded (vector/hybrid) search.
 */
class VectorSearchPresets
{
    public const STRATEGY_PARALLEL = 'parallel';
    public const STRATEGY_BLENDED  = 'blended';
    public const STRATEGY_RESCUE   = 'rescue';

    public const STRATEGIES = [
        self::STRATEGY_PARALLEL,
        self::STRATEGY_BLENDED,
        self::STRATEGY_RESCUE,
    ];

    public const DEFAULT_STRATEGY = self::STRATEGY_PARALLEL;

    public const PROFILE_BALANCED  = 'balanced';
    public const PROFILE_STRICT    = 'strict';
    public const PROFILE_DISCOVERY = 'discovery';
    public const PROFILE_CUSTOM    = 'custom';

    public const PROFILES = [
        self::PROFILE_BALANCED,
        self::PROFILE_STRICT,
        self::PROFILE_DISCOVERY,
        self::PROFILE_CUSTOM,
    ];

    public const DEFAULT_PROFILE = self::PROFILE_BALANCED;

    public const SUBSITE_STANDARD     = 'standard';
    public const SUBSITE_TIGHTENED    = 'tightened';
    public const SUBSITE_KEYWORD_ONLY = 'keyword_only';

    public const SUBSITE_PROFILES = [
        self::SUBSITE_STANDARD,
        self::SUBSITE_TIGHTENED,
        self::SUBSITE_KEYWORD_ONLY,
    ];

    public const DEFAULT_SUBSITE_PROFILE = self::SUBSITE_STANDARD;

    /**
     * Visitor-facing UX strategies selectable by site administrators.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public static function strategies(): array
    {
        return [
            self::STRATEGY_PARALLEL => [
                'label'       => __('Top matches box + keyword results (Recommended)', 'typesense-search'),
                'description' => __(
                    'Displays up to 3 high-confidence AI shortcuts in a compact box above Page 1 for multi-word searches, while keeping predictable keyword search, highlights, and facet counts underneath.',
                    'typesense-search'
                ),
            ],
            self::STRATEGY_BLENDED => [
                'label'       => __('Blended hybrid list', 'typesense-search'),
                'description' => __(
                    'Combines keyword and semantic matches into one unified result list. Exact word matches stay first, while meaning-only matches show a subtle "Related match" badge.',
                    'typesense-search'
                ),
            ],
            self::STRATEGY_RESCUE => [
                'label'       => __('Keyword first + semantic rescue', 'typesense-search'),
                'description' => __(
                    'Uses exact keyword search normally and only adds a "Related pages" section when keyword search finds few or zero matches.',
                    'typesense-search'
                ),
            ],
        ];
    }

    /**
     * Site-level ranking preset profiles with plain-language descriptions.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public static function rankingProfiles(): array
    {
        return [
            self::PROFILE_BALANCED => [
                'label'       => __('Balanced (Recommended)', 'typesense-search'),
                'description' => __(
                    'Prioritizes exact words and phrases first while surfacing strong meaning matches for questions, synonyms, and everyday phrasing. Best for general municipality and organization sites.',
                    'typesense-search'
                ),
            ],
            self::PROFILE_STRICT => [
                'label'       => __('Strict — High precision / smaller sites', 'typesense-search'),
                'description' => __(
                    'Tightens semantic matching so only very close meaning matches appear. Best for smaller sites, intranets, or formal directories where loosely related suggestions are distracting.',
                    'typesense-search'
                ),
            ],
            self::PROFILE_DISCOVERY => [
                'label'       => __('Conversational — Broad intent matching', 'typesense-search'),
                'description' => __(
                    'Gives stronger weight to meaning and natural-language questions so visitors using everyday phrasing still find relevant service pages and guides.',
                    'typesense-search'
                ),
            ],
            self::PROFILE_CUSTOM => [
                'label'       => __('Custom — Fine-tune parameters manually', 'typesense-search'),
                'description' => __(
                    'Manually configure keyword/meaning balance, cosine distance cutoffs, candidate pool size, and spotlight or rescue thresholds.',
                    'typesense-search'
                ),
            ],
        ];
    }

    /**
     * Per-target sibling relevance profiles for cross-site multi-search.
     *
     * @return array<string, array{short_label: string, label: string, description: string}>
     */
    public static function subsiteProfiles(): array
    {
        return [
            self::SUBSITE_STANDARD => [
                'short_label' => __('Standard (Full hybrid)', 'typesense-search'),
                'label'       => __('Standard — Full hybrid matching', 'typesense-search'),
                'description' => __(
                    'Uses the searching site\'s normal ranking profile. Best for main portals and large sibling sites with broad content.',
                    'typesense-search'
                ),
            ],
            self::SUBSITE_TIGHTENED => [
                'short_label' => __('Tightened (Small / niche subsite)', 'typesense-search'),
                'label'       => __('Tightened — Small or niche subsite', 'typesense-search'),
                'description' => __(
                    'Requires an exact keyword match or a very close meaning match before pages from this site appear. Prevents smaller or specialized subsites (such as a venue, unit, or event site) from pushing loosely related pages into results.',
                    'typesense-search'
                ),
            ],
            self::SUBSITE_KEYWORD_ONLY => [
                'short_label' => __('Keyword only (Exact words)', 'typesense-search'),
                'label'       => __('Keyword only — Exact words only', 'typesense-search'),
                'description' => __(
                    'Includes pages from this site only when they contain the visitor\'s typed search words (no semantic matching). Best for archives or external registries.',
                    'typesense-search'
                ),
            ],
        ];
    }

    public static function sanitizeStrategy(mixed $value): string
    {
        $candidate = is_string($value) ? trim($value) : '';

        return in_array($candidate, self::STRATEGIES, true) ? $candidate : self::DEFAULT_STRATEGY;
    }

    public static function sanitizeProfile(mixed $value): string
    {
        $candidate = is_string($value) ? trim($value) : '';

        return in_array($candidate, self::PROFILES, true) ? $candidate : self::DEFAULT_PROFILE;
    }

    public static function sanitizeSubsiteProfile(mixed $value): string
    {
        $candidate = is_string($value) ? trim($value) : '';

        return in_array($candidate, self::SUBSITE_PROFILES, true) ? $candidate : self::DEFAULT_SUBSITE_PROFILE;
    }

    /**
     * Built-in parameter values for a named preset profile.
     *
     * @return array{
     *     alpha: float,
     *     distance_threshold: float,
     *     k: int,
     *     rerank_hybrid_matches: bool,
     *     spotlight_max_hits: int,
     *     spotlight_distance_threshold: float,
     *     spotlight_min_words: int,
     *     spotlight_dedupe: bool,
     *     rescue_max_keyword_hits: int
     * }
     */
    public static function presetParams(string $profile): array
    {
        return match ($profile) {
            self::PROFILE_STRICT => [
                'alpha'                        => 0.20,
                'distance_threshold'           => 0.45,
                'k'                            => 50,
                'rerank_hybrid_matches'        => true,
                'spotlight_max_hits'           => 2,
                'spotlight_distance_threshold' => 0.36,
                'spotlight_min_words'          => 2,
                'spotlight_dedupe'             => true,
                'rescue_max_keyword_hits'      => 0,
            ],
            self::PROFILE_DISCOVERY => [
                'alpha'                        => 0.50,
                'distance_threshold'           => 0.72,
                'k'                            => 150,
                'rerank_hybrid_matches'        => true,
                'spotlight_max_hits'           => 3,
                'spotlight_distance_threshold' => 0.52,
                'spotlight_min_words'          => 2,
                'spotlight_dedupe'             => true,
                'rescue_max_keyword_hits'      => 3,
            ],
            default => [
                'alpha'                        => 0.30,
                'distance_threshold'           => 0.60,
                'k'                            => 100,
                'rerank_hybrid_matches'        => true,
                'spotlight_max_hits'           => 3,
                'spotlight_distance_threshold' => 0.45,
                'spotlight_min_words'          => 2,
                'spotlight_dedupe'             => true,
                'rescue_max_keyword_hits'      => 2,
            ],
        };
    }

    /**
     * Sanitize and clamp custom ranking parameters submitted from the admin form.
     *
     * @return array{
     *     alpha: float,
     *     distance_threshold: float,
     *     k: int,
     *     rerank_hybrid_matches: bool,
     *     spotlight_max_hits: int,
     *     spotlight_distance_threshold: float,
     *     spotlight_min_words: int,
     *     spotlight_dedupe: bool,
     *     rescue_max_keyword_hits: int
     * }
     */
    public static function sanitizeCustomParams(mixed $raw): array
    {
        $defaults = self::presetParams(self::PROFILE_BALANCED);
        if (!is_array($raw)) {
            return $defaults;
        }

        $alpha = isset($raw['alpha']) && is_numeric($raw['alpha'])
            ? round(min(0.95, max(0.05, (float) $raw['alpha'])), 2)
            : $defaults['alpha'];

        $distanceThreshold = isset($raw['distance_threshold']) && is_numeric($raw['distance_threshold'])
            ? round(min(1.20, max(0.15, (float) $raw['distance_threshold'])), 2)
            : $defaults['distance_threshold'];

        $k = isset($raw['k']) && is_numeric($raw['k'])
            ? min(250, max(10, (int) $raw['k']))
            : $defaults['k'];

        $rerank = array_key_exists('rerank_hybrid_matches', $raw)
            ? !empty($raw['rerank_hybrid_matches'])
            : $defaults['rerank_hybrid_matches'];

        $spotlightMaxHits = isset($raw['spotlight_max_hits']) && is_numeric($raw['spotlight_max_hits'])
            ? min(5, max(1, (int) $raw['spotlight_max_hits']))
            : $defaults['spotlight_max_hits'];

        $spotlightThreshold = isset($raw['spotlight_distance_threshold']) && is_numeric($raw['spotlight_distance_threshold'])
            ? round(min(0.90, max(0.15, (float) $raw['spotlight_distance_threshold'])), 2)
            : $defaults['spotlight_distance_threshold'];

        $spotlightMinWords = isset($raw['spotlight_min_words']) && is_numeric($raw['spotlight_min_words'])
            ? min(6, max(1, (int) $raw['spotlight_min_words']))
            : $defaults['spotlight_min_words'];

        $spotlightDedupe = array_key_exists('spotlight_dedupe', $raw)
            ? !empty($raw['spotlight_dedupe'])
            : $defaults['spotlight_dedupe'];

        $rescueMaxHits = isset($raw['rescue_max_keyword_hits']) && is_numeric($raw['rescue_max_keyword_hits'])
            ? min(10, max(0, (int) $raw['rescue_max_keyword_hits']))
            : $defaults['rescue_max_keyword_hits'];

        return [
            'alpha'                        => $alpha,
            'distance_threshold'           => $distanceThreshold,
            'k'                            => $k,
            'rerank_hybrid_matches'        => $rerank,
            'spotlight_max_hits'           => $spotlightMaxHits,
            'spotlight_distance_threshold' => $spotlightThreshold,
            'spotlight_min_words'          => $spotlightMinWords,
            'spotlight_dedupe'             => $spotlightDedupe,
            'rescue_max_keyword_hits'      => $rescueMaxHits,
        ];
    }

    /**
     * Resolve effective runtime parameters (including derived tight Quick Search bounds)
     * from the selected profile and optional custom overrides.
     *
     * @return array{
     *     alpha: float,
     *     distance_threshold: float,
     *     k: int,
     *     rerank_hybrid_matches: bool,
     *     spotlight_max_hits: int,
     *     spotlight_distance_threshold: float,
     *     spotlight_min_words: int,
     *     spotlight_dedupe: bool,
     *     rescue_max_keyword_hits: int,
     *     quick_alpha: float,
     *     quick_distance_threshold: float,
     *     quick_k: int
     * }
     */
    public static function resolveParams(string $profile, mixed $customParams = []): array
    {
        $sanitizedProfile = self::sanitizeProfile($profile);
        $base = $sanitizedProfile === self::PROFILE_CUSTOM
            ? self::sanitizeCustomParams($customParams)
            : self::presetParams($sanitizedProfile);

        $quickAlpha     = round(min($base['alpha'], 0.25), 2);
        $quickThreshold = round(min($base['distance_threshold'], max(0.36, $base['spotlight_distance_threshold'] + 0.03)), 2);
        $quickK         = min($base['k'], 30);

        return $base + [
            'quick_alpha'              => $quickAlpha,
            'quick_distance_threshold' => $quickThreshold,
            'quick_k'                  => $quickK,
        ];
    }
}
