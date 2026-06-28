<?php

namespace TypesenseSearch\Services;

use TypesenseSearch\Admin\Settings;
use TypesenseSearch\Helper\PdfToText;

/**
 * Class SettingsRepository
 *
 * Centralised, type-safe access to plugin WordPress options used at runtime.
 * Defaults, type coercions, and validation live here for behaviour-critical
 * settings. Settings views and form rendering may still read options directly.
 *
 * @package TypesenseSearch\Services
 */
class SettingsRepository
{
    // ── Connection ──────────────────────────────────────────────────────────

    public function getRemote(): string
    {
        return (string) get_option(Settings::OPTION_REMOTE, '');
    }

    public function getAdminKey(): string
    {
        return (string) get_option(Settings::OPTION_ADMIN_KEY, '');
    }

    public function getSearchKey(): string
    {
        return (string) get_option(Settings::OPTION_SEARCH_KEY, '');
    }

    public function getCollectionName(): string
    {
        return (string) get_option(Settings::OPTION_INDEX_NAME, '');
    }

    public function getFrontendHost(): string
    {
        return (string) get_option(Settings::OPTION_FRONTEND_HOST, '');
    }

    // ── Content ─────────────────────────────────────────────────────────────

    /**
     * Return the list of post-type slugs enabled for indexing.
     *
     * @return string[]
     */
    public function getEnabledPostTypes(): array
    {
        return (array) get_option(Settings::OPTION_POST_TYPES, []);
    }

    public function isPostTypeEnabled(string $postType): bool
    {
        return in_array($postType, $this->getEnabledPostTypes(), true);
    }

    public function isIndexPdfEnabled(): bool
    {
        return (bool) get_option(Settings::OPTION_INDEX_PDF, 0);
    }

    public function isIndexModularityEnabled(): bool
    {
        return (bool) get_option(Settings::OPTION_INDEX_MODULARITY, 0);
    }

    // ── Search UI ───────────────────────────────────────────────────────────

    public function getHitsPerPage(): int
    {
        return max(1, (int) get_option(Settings::OPTION_HITS_PER_PAGE, 10));
    }

    public function isDebounceEnabled(): bool
    {
        return (bool) get_option(Settings::OPTION_DEBOUNCE, true);
    }

    public function getDebounceDelay(): int
    {
        return max(0, (int) get_option(Settings::OPTION_DEBOUNCE_DELAY, 300));
    }

    public function getHighlightAffixNumTokens(): int
    {
        return max(1, (int) get_option(Settings::OPTION_HIGHLIGHT_AFFIX_NUM_TOKENS, 15));
    }

    public function getTruncator(): string
    {
        return (string) get_option(Settings::OPTION_TRUNCATOR, '[...]');
    }

    public function getSortDisplay(): string
    {
        $raw = (string) get_option(Settings::OPTION_SORT_DISPLAY, 'radio');

        return in_array($raw, ['radio', 'dropdown'], true) ? $raw : 'radio';
    }

    public function getQueryByWeights(): string
    {
        $weights = array_merge(
            Settings::getDefaultQueryByWeights(),
            (array) get_option(Settings::OPTION_QUERY_BY_WEIGHTS, [])
        );

        $queryByOrder = ['title', 'excerpt', 'content', 'extra_terms', 'type_name'];
        $values = array_map(
            static fn (string $field): int => min(5, max(1, (int) ($weights[$field] ?? 1))),
            $queryByOrder
        );

        return implode(',', $values);
    }

    public function isPinnedResultsEnabled(): bool
    {
        return (bool) get_option(Settings::OPTION_PINNED_RESULTS_ENABLED, 0);
    }

    // ── Search statistics ──────────────────────────────────────────────────

    public function isSearchLoggingEnabled(): bool
    {
        return (bool) get_option(Settings::OPTION_SEARCH_LOGGING_ENABLED, 0);
    }

    public function areSearchStatisticsDashboardWidgetsEnabled(): bool
    {
        return (bool) get_option(Settings::OPTION_SEARCH_LOGGING_DASHBOARD_WIDGETS, 1);
    }

    public function doesSearchLoggingRequireConsent(): bool
    {
        return (bool) get_option(Settings::OPTION_SEARCH_LOGGING_REQUIRE_CONSENT, 0);
    }

    public function getSearchLoggingDelayMilliseconds(): int
    {
        return min(30000, max(0, (int) get_option(Settings::OPTION_SEARCH_LOGGING_DELAY_SECONDS, 1) * 1000));
    }

    public function getSearchLoggingMinimumCharacters(): int
    {
        return min(50, max(1, (int) get_option(Settings::OPTION_SEARCH_LOGGING_MINIMUM_CHARACTERS, 3)));
    }

    public function getSearchStatisticsRetentionDays(): int
    {
        return min(3650, max(1, (int) get_option(Settings::OPTION_SEARCH_STATISTICS_RETENTION_DAYS, 90)));
    }

    // ── Facets ───────────────────────────────────────────────────────────────

    /**
     * Return the configured facet definitions.
     *
     * @return array<int, array<string, string>>
     */
    public function getFacets(): array
    {
        return (array) get_option(Settings::OPTION_FACETS, []);
    }

    // ── Quick search ────────────────────────────────────────────────────────

    public function isQuickSearchEnabled(): bool
    {
        return (bool) get_option(Settings::OPTION_QUICK_SEARCH_ENABLED, 0);
    }

    /**
     * @return array<int, array{selector: string, sibling: bool, mobile_behavior?: 'regular'|'overlay'}>
     */
    public function getQuickSearchSelectors(): array
    {
        return (array) get_option(Settings::OPTION_QUICK_SEARCH_SELECTORS, []);
    }

    public function getQuickSearchHitsPerPage(): int
    {
        return max(1, (int) get_option(Settings::OPTION_QUICK_SEARCH_HITS_PER_PAGE, 5));
    }

    // ── Environment helpers ─────────────────────────────────────────────────
    // These do not read stored options; they inspect the server environment or
    // the active plugin list. Kept static so existing static call sites can
    // migrate without requiring a SettingsRepository instance.

    /**
     * Return all public, indexable post types (excluding attachments).
     *
     * @return \WP_Post_Type[]
     */
    public static function getIndexablePostTypes(): array
    {
        $postTypes = get_post_types(['public' => true], 'objects');
        unset($postTypes['attachment']);

        return $postTypes;
    }

    /**
     * Check whether the pdftotext binary is available on the server.
     *
     * Delegates to {@see PdfToText::isAvailable()} — use that class directly
     * when you also need the binary path or text extraction.
     */
    public static function isPdfToTextAvailable(): bool
    {
        return PdfToText::isAvailable();
    }

    /**
     * Check whether the Modularity plugin is available.
     */
    public static function isModularityAvailable(): bool
    {
        return class_exists('\\Modularity\\App');
    }
}
