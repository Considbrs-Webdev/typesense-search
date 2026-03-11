<?php

namespace TypesenseSearch\Services;

use TypesenseSearch\Admin\Settings;

/**
 * Class SettingsRepository
 *
 * Centralised, type-safe access to every WordPress option used by the plugin.
 * All get_option() calls for plugin settings are consolidated here so that
 * defaults, type coercions, and validation live in one place rather than being
 * scattered across strategies, frontend classes, and CLI commands.
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
     * @return array<int, array{selector: string, sibling: bool}>
     */
    public function getQuickSearchSelectors(): array
    {
        return (array) get_option(Settings::OPTION_QUICK_SEARCH_SELECTORS, []);
    }

    public function getQuickSearchHitsPerPage(): int
    {
        return max(1, (int) get_option(Settings::OPTION_QUICK_SEARCH_HITS_PER_PAGE, 5));
    }
}
