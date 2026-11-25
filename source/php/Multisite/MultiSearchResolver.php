<?php

namespace TypesenseSearch\Multisite;

use TypesenseSearch\Admin\Settings\OptionKeys;
use TypesenseSearch\Typesense\SchemaInspector;
use TypesenseSearch\Typesense\VectorSearchPresets;

/**
 * Resolves cross-site multi-search configuration and runtime eligibility
 * from saved network/blog options without switching blogs or calling Typesense.
 */
class MultiSearchResolver
{
    public function __construct(
        private NetworkSettingsRepository $network = new NetworkSettingsRepository(),
    ) {
    }

    /**
     * Sanitize a raw submitted or stored map of `source_blog_id => target_blog_id[]`
     * against the network's enabled site list.
     *
     * @param mixed $raw
     * @param int[] $enabledSiteIds
     * @return array<int, int[]>
     */
    public static function sanitizeMap(mixed $raw, array $enabledSiteIds): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $enabledLookup = array_fill_keys(array_map('intval', $enabledSiteIds), true);
        $clean         = [];

        foreach ($raw as $sourceKey => $targets) {
            $sourceId = absint($sourceKey);
            if ($sourceId <= 0 || !isset($enabledLookup[$sourceId]) || !is_array($targets)) {
                continue;
            }

            $validTargets = [];
            foreach ($targets as $targetRaw) {
                $targetId = absint($targetRaw);
                if ($targetId <= 0 || $targetId === $sourceId || !isset($enabledLookup[$targetId])) {
                    continue;
                }
                $validTargets[$targetId] = $targetId;
            }

            if ($validTargets !== []) {
                $clean[$sourceId] = array_values($validTargets);
            }
        }

        ksort($clean);

        return $clean;
    }

    /**
     * Sanitize per-target relevance profiles (`source_blog_id => [target_blog_id => profile]`)
     * against the already-sanitized target map so only active source-target pairs are kept.
     *
     * @param mixed             $rawProfiles
     * @param array<int, int[]> $cleanMap
     * @return array<int, array<int, string>>
     */
    public static function sanitizeProfiles(mixed $rawProfiles, array $cleanMap): array
    {
        if (!is_array($rawProfiles) || $cleanMap === []) {
            return [];
        }

        $clean = [];
        foreach ($cleanMap as $sourceId => $targetIds) {
            $sourceRaw = $rawProfiles[$sourceId] ?? $rawProfiles[(string) $sourceId] ?? null;
            if (!is_array($sourceRaw)) {
                continue;
            }

            $profilesForSource = [];
            foreach ($targetIds as $targetId) {
                $rawProfile = $sourceRaw[$targetId] ?? $sourceRaw[(string) $targetId] ?? VectorSearchPresets::DEFAULT_SUBSITE_PROFILE;
                $sanitized  = VectorSearchPresets::sanitizeSubsiteProfile($rawProfile);
                if ($sanitized !== VectorSearchPresets::DEFAULT_SUBSITE_PROFILE) {
                    $profilesForSource[$targetId] = $sanitized;
                }
            }

            if ($profilesForSource !== []) {
                ksort($profilesForSource);
                $clean[$sourceId] = $profilesForSource;
            }
        }

        ksort($clean);

        return $clean;
    }

    /**
     * Configured subsite relevance profiles keyed by target blog ID for a source blog ID.
     *
     * @return array<int, string>
     */
    public function configuredTargetProfilesFor(int $sourceBlogId): array
    {
        $targets  = $this->configuredTargetsFor($sourceBlogId);
        $savedMap = $this->network->multiSearchProfiles()[$sourceBlogId] ?? [];
        $result   = [];

        foreach ($targets as $targetId) {
            $result[$targetId] = VectorSearchPresets::sanitizeSubsiteProfile(
                $savedMap[$targetId] ?? VectorSearchPresets::DEFAULT_SUBSITE_PROFILE
            );
        }

        return $result;
    }

    /**
     * Configured target blog IDs for a given source blog ID.
     *
     * @return int[]
     */
    public function configuredTargetsFor(int $sourceBlogId): array
    {
        $map = $this->network->multiSearchMap();
        $raw = $map[$sourceBlogId] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $targets = [];
        foreach ($raw as $id) {
            $targetId = absint($id);
            if ($targetId > 0 && $targetId !== $sourceBlogId) {
                $targets[$targetId] = $targetId;
            }
        }

        return array_values($targets);
    }

    /**
     * Resolve runtime multi-search collections, skipped targets, and vector
     * search compatibility for the current site.
     *
     * @return array{
     *     configured: bool,
     *     enabled: bool,
     *     collections: array<int, array{
     *         blog_id: int,
     *         site_name: string,
     *         home_url: string,
     *         collection: string,
     *         search_key: string,
     *         enable_curations: bool,
     *         vector_enabled: bool,
     *         vector_ready: bool
     *     }>,
     *     skipped_targets: array<int, array{
     *         blog_id: int,
     *         site_name: string,
     *         home_url: string,
     *         reason: string
     *     }>,
     *     vector_compatible: bool,
     *     vector_paused_reason: string
     * }
     */
    public function resolveForCurrentSite(): array
    {
        if (!$this->network->isNetworkActivated()) {
            return $this->emptyResolution();
        }

        return $this->resolveForSite((int) get_current_blog_id());
    }

    /**
     * Resolve multi-search state for an arbitrary blog ID in the current network.
     *
     * @return array{
     *     configured: bool,
     *     enabled: bool,
     *     collections: array<int, array{
     *         blog_id: int,
     *         site_name: string,
     *         home_url: string,
     *         collection: string,
     *         search_key: string,
     *         enable_curations: bool,
     *         vector_enabled: bool,
     *         vector_ready: bool
     *     }>,
     *     skipped_targets: array<int, array{
     *         blog_id: int,
     *         site_name: string,
     *         home_url: string,
     *         reason: string
     *     }>,
     *     vector_compatible: bool,
     *     vector_paused_reason: string
     * }
     */
    public function resolveForSite(int $sourceBlogId): array
    {
        if (!$this->network->isNetworkActivated() || $sourceBlogId <= 0) {
            return $this->emptyResolution();
        }

        $targetIds = $this->configuredTargetsFor($sourceBlogId);
        if ($targetIds === []) {
            return $this->emptyResolution();
        }

        $connection  = $this->network->connection();
        $networkId   = $this->network->networkId();
        $enabledIds  = array_map('intval', (array) get_network_option($networkId, NetworkSettingsRepository::ENABLED, []));
        $enabledSet  = array_fill_keys($enabledIds, true);
        $vectorModel = $this->network->vectorModel();

        $sourceCheck = $this->inspectSite($sourceBlogId, $connection, $networkId, isset($enabledSet[$sourceBlogId]), $vectorModel, true);
        if ($sourceCheck['entry'] === null) {
            return [
                'configured'           => true,
                'enabled'              => false,
                'collections'          => [],
                'skipped_targets'      => [],
                'vector_compatible'    => false,
                'vector_paused_reason' => '',
            ];
        }

        $collections    = [$sourceCheck['entry']];
        $skippedTargets = [];
        $targetProfiles = $this->configuredTargetProfilesFor($sourceBlogId);

        foreach ($targetIds as $targetId) {
            $targetCheck = $this->inspectSite(
                $targetId,
                $connection,
                $networkId,
                isset($enabledSet[$targetId]),
                $vectorModel,
                false,
                $targetProfiles[$targetId] ?? VectorSearchPresets::DEFAULT_SUBSITE_PROFILE
            );

            if ($targetCheck['entry'] !== null) {
                $collections[] = $targetCheck['entry'];
            } else {
                $skippedTargets[] = [
                    'blog_id'   => $targetId,
                    'site_name' => $this->siteLabel($targetId),
                    'home_url'  => (string) get_home_url($targetId),
                    'reason'    => $targetCheck['reason'],
                ];
            }
        }

        // Need at least one valid sibling collection in addition to the source site.
        if (count($collections) < 2) {
            return [
                'configured'           => true,
                'enabled'              => false,
                'collections'          => [],
                'skipped_targets'      => $skippedTargets,
                'vector_compatible'    => false,
                'vector_paused_reason' => '',
            ];
        }

        $sourceEntry        = $collections[0];
        $vectorCompatible   = false;
        $vectorPausedReason = '';
        $sourceStrategy     = VectorSearchPresets::sanitizeStrategy(
            get_blog_option($sourceBlogId, OptionKeys::OPTION_VECTOR_STRATEGY, VectorSearchPresets::DEFAULT_STRATEGY)
        );

        if ($sourceEntry['vector_enabled'] && $sourceEntry['vector_ready']) {
            $incompatibleLabels = [];
            foreach (array_slice($collections, 1) as $sibling) {
                // In Top Matches (parallel) and Semantic Rescue strategies, the main union
                // list is pure keyword and vector queries skip `keyword_only` siblings, so a
                // `keyword_only` sibling does not require an embedded index. Blended hybrid
                // union requires an embedding field on every union leg in Typesense.
                if (
                    $sibling['subsite_profile'] === VectorSearchPresets::SUBSITE_KEYWORD_ONLY
                    && $sourceStrategy !== VectorSearchPresets::STRATEGY_BLENDED
                ) {
                    continue;
                }

                if (!$sibling['vector_ready']) {
                    if (!$sibling['vector_enabled']) {
                        $incompatibleLabels[] = sprintf(
                            /* translators: %s: sibling site name */
                            __('%s (embedded search disabled)', 'typesense-search'),
                            $sibling['site_name']
                        );
                    } else {
                        $incompatibleLabels[] = sprintf(
                            /* translators: 1: sibling site name, 2: embedding model */
                            __('%1$s (rebuild required for model "%2$s")', 'typesense-search'),
                            $sibling['site_name'],
                            $vectorModel
                        );
                    }
                }
            }

            if ($incompatibleLabels === []) {
                $vectorCompatible = true;
            } else {
                $vectorPausedReason = sprintf(
                    /* translators: %s: comma-separated list of sibling sites and reasons */
                    __(
                        'Embedded search is temporarily paused for cross-site search because every queried index must share the active embedding model: %s. Cross-site search is running safely in keyword mode until resolved.',
                        'typesense-search'
                    ),
                    implode(', ', $incompatibleLabels)
                );
            }
        }

        return [
            'configured'           => true,
            'enabled'              => true,
            'collections'          => $collections,
            'skipped_targets'      => $skippedTargets,
            'vector_compatible'    => $vectorCompatible,
            'vector_paused_reason' => $vectorPausedReason,
        ];
    }

    /**
     * @param array{remote: string, admin_key: string, frontend_host: string} $connection
     * @return array{
     *     entry: array{
     *         blog_id: int,
     *         site_name: string,
     *         home_url: string,
     *         collection: string,
     *         search_key: string,
     *         enable_curations: bool,
     *         vector_enabled: bool,
     *         vector_ready: bool
     *     }|null,
     *     reason: string
     * }
     */
    private function inspectSite(
        int $blogId,
        array $connection,
        int $networkId,
        bool $isSelected,
        string $vectorModel,
        bool $enableCurations,
        string $subsiteProfile = VectorSearchPresets::DEFAULT_SUBSITE_PROFILE
    ): array {
        if ($this->network->conflict()) {
            return ['entry' => null, 'reason' => __('Global constants conflict with network mode.', 'typesense-search')];
        }
        if ($connection['remote'] === '' || $connection['admin_key'] === '') {
            return ['entry' => null, 'reason' => __('Network connection is not configured.', 'typesense-search')];
        }

        $site = get_site($blogId);
        if (!$site || (int) ($site->network_id ?? 0) !== $networkId || !empty($site->archived) || !empty($site->spam) || !empty($site->deleted)) {
            return ['entry' => null, 'reason' => __('Site is archived, deleted, or unavailable.', 'typesense-search')];
        }
        if (!$isSelected) {
            return ['entry' => null, 'reason' => __('Site is disabled in Network Admin.', 'typesense-search')];
        }

        $homeUrl  = rtrim((string) get_home_url($blogId), '/');
        $identity = [
            'home'        => $homeUrl,
            'environment' => wp_get_environment_type(),
            'remote'      => rtrim($connection['remote'], '/'),
            'network'     => $networkId,
            'site'        => $blogId,
            'prefix'      => $this->network->prefix(),
        ];

        $state   = (array) get_blog_option($blogId, NetworkSettingsRepository::STATE, []);
        $mapping = (array) ($state['active'] ?? []);

        if (
            ($mapping['identity'] ?? null) !== $identity
            || empty($mapping['collection'])
            || empty($mapping['key'])
        ) {
            return ['entry' => null, 'reason' => __('Site index setup is incomplete or out of date.', 'typesense-search')];
        }

        $collection          = (string) $mapping['collection'];
        $vectorEnabled       = (bool) get_blog_option($blogId, OptionKeys::OPTION_VECTOR_ENABLED, 0);
        $hasMatchingEmbedding = SchemaInspector::isEmbeddingReady(
            $connection['remote'],
            $collection,
            $vectorModel,
            $blogId,
            $connection['admin_key']
        );
        // The source site must explicitly opt into embedded search on its search page;
        // a sibling target collection is compatible whenever its live Typesense schema
        // already stores embeddings for the shared network model.
        $vectorReady = $enableCurations
            ? ($vectorEnabled && $hasMatchingEmbedding)
            : $hasMatchingEmbedding;

        return [
            'entry' => [
                'blog_id'          => $blogId,
                'site_name'        => $this->siteLabel($blogId),
                'home_url'         => $homeUrl,
                'collection'       => $collection,
                'search_key'       => (string) $mapping['key'],
                'enable_curations' => $enableCurations,
                'subsite_profile'  => VectorSearchPresets::sanitizeSubsiteProfile($subsiteProfile),
                'vector_enabled'   => $vectorEnabled,
                'vector_ready'     => $vectorReady,
            ],
            'reason' => '',
        ];
    }

    private function siteLabel(int $blogId): string
    {
        $name = trim((string) get_blog_option($blogId, 'blogname', ''));
        if ($name !== '') {
            return $name;
        }

        $home = (string) get_home_url($blogId);
        $host = (string) parse_url($home, PHP_URL_HOST);
        $path = trim((string) parse_url($home, PHP_URL_PATH), '/');

        if ($host !== '') {
            return $path !== '' ? "{$host}/{$path}" : $host;
        }

        return sprintf('Site #%d', $blogId);
    }

    /**
     * @return array{
     *     configured: false,
     *     enabled: false,
     *     collections: array{},
     *     skipped_targets: array{},
     *     vector_compatible: false,
     *     vector_paused_reason: string
     * }
     */
    private function emptyResolution(): array
    {
        return [
            'configured'           => false,
            'enabled'              => false,
            'collections'          => [],
            'skipped_targets'      => [],
            'vector_compatible'    => false,
            'vector_paused_reason' => '',
        ];
    }
}
