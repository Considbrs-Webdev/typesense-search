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
        self::STRATEGY_BLENDED,
        self::STRATEGY_PARALLEL,
        self::STRATEGY_RESCUE,
    ];

    public const DEFAULT_STRATEGY = self::STRATEGY_BLENDED;

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
            self::STRATEGY_BLENDED => [
                'label'       => __('Blended hybrid list (Recommended)', 'typesense-search'),
                'description' => __(
                    'Combines keyword and semantic meaning into one unified result list so the best answer ranks right at the top. Pure meaning matches display a subtle "Related match" badge.',
                    'typesense-search'
                ),
            ],
            self::STRATEGY_PARALLEL => [
                'label'       => __('Top matches box + keyword results', 'typesense-search'),
                'description' => __(
                    'Displays up to 3 high-confidence AI shortcuts in a compact box above Page 1, while keeping exact keyword results and highlights underneath.',
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
     * Contrastive E5 models (`ts/multilingual-e5-*`) compress cosine distances into
     * a narrow `[0.08, 0.30]` cone, whereas symmetric encoders such as MPNet and MiniLM
     * span `[0.15, 1.00]`.
     */
    public static function isNarrowConeModel(string $model): bool
    {
        return str_contains(strtolower(trim($model)), 'e5');
    }

    /**
     * Built-in parameter values for a named preset profile, calibrated to the
     * active embedding model family.
     *
     * Note: `rerank_hybrid_matches` defaults to `false` across built-in presets
     * because Typesense's auxiliary re-ranker runs an un-intersected OR across
     * query function words and breaks 0-keyword synonym ties by `seq_id`.
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
    public static function presetParams(string $profile, string $model = ''): array
    {
        $narrowCone = self::isNarrowConeModel($model);

        return match ($profile) {
            self::PROFILE_STRICT => [
                'alpha'                        => 0.40,
                'distance_threshold'           => $narrowCone ? 0.20 : 0.62,
                'k'                            => 60,
                'rerank_hybrid_matches'        => false,
                'spotlight_max_hits'           => 2,
                'spotlight_distance_threshold' => $narrowCone ? 0.18 : 0.56,
                'spotlight_min_words'          => 2,
                'spotlight_dedupe'             => true,
                'rescue_max_keyword_hits'      => 0,
            ],
            self::PROFILE_DISCOVERY => [
                'alpha'                        => 0.70,
                'distance_threshold'           => $narrowCone ? 0.28 : 0.82,
                'k'                            => 150,
                'rerank_hybrid_matches'        => false,
                'spotlight_max_hits'           => 3,
                'spotlight_distance_threshold' => $narrowCone ? 0.25 : 0.75,
                'spotlight_min_words'          => 1,
                'spotlight_dedupe'             => true,
                'rescue_max_keyword_hits'      => 5,
            ],
            default => [
                'alpha'                        => 0.58,
                'distance_threshold'           => $narrowCone ? 0.25 : 0.75,
                'k'                            => 100,
                'rerank_hybrid_matches'        => false,
                'spotlight_max_hits'           => 3,
                'spotlight_distance_threshold' => $narrowCone ? 0.22 : 0.68,
                'spotlight_min_words'          => 1,
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
    public static function sanitizeCustomParams(mixed $raw, string $model = ''): array
    {
        $defaults = self::presetParams(self::PROFILE_BALANCED, $model);
        if (!is_array($raw)) {
            return $defaults;
        }

        $alpha = isset($raw['alpha']) && is_numeric($raw['alpha'])
            ? round(min(0.95, max(0.05, (float) $raw['alpha'])), 2)
            : $defaults['alpha'];

        $distanceThreshold = isset($raw['distance_threshold']) && is_numeric($raw['distance_threshold'])
            ? round(min(1.20, max(0.10, (float) $raw['distance_threshold'])), 2)
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
            ? round(min(1.00, max(0.10, (float) $raw['spotlight_distance_threshold'])), 2)
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
     * from the selected profile, optional custom overrides, and active embedding model.
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
    public static function resolveParams(string $profile, mixed $customParams = [], string $model = ''): array
    {
        $sanitizedProfile = self::sanitizeProfile($profile);
        $base = $sanitizedProfile === self::PROFILE_CUSTOM
            ? self::sanitizeCustomParams($customParams, $model)
            : self::presetParams($sanitizedProfile, $model);

        $quickAlpha     = round(min($base['alpha'], 0.58), 2);
        $quickThreshold = round(min($base['distance_threshold'], $base['spotlight_distance_threshold']), 2);
        $quickK         = min($base['k'], 40);

        return $base + [
            'quick_alpha'              => $quickAlpha,
            'quick_distance_threshold' => $quickThreshold,
            'quick_k'                  => $quickK,
        ];
    }
}
