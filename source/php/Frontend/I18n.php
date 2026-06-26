<?php

namespace TypesenseSearch\Frontend;

/**
 * Class I18n
 *
 * Collects all translatable strings for the frontend JS bundle.
 * Passed to the browser via wp_localize_script as `window.typesenseI18n`.
 *
 * @package TypesenseSearch\Frontend
 */
class I18n
{
    /**
     * Returns an array of all translatable strings for the frontend JS bundle.
     *
     * @return array<string, string>
     */
    public static function strings(): array
    {
        return [
            // search.ts – result count
            'resultSingular'   => __('%d result', 'typesense-search'),
            'resultPlural'     => __('%d results', 'typesense-search'),

            // search.ts – error state
            'searchError'      => __('Search failed. Please try again.', 'typesense-search'),

            // Web Awesome wa-input clear (override for Swedish; see webawesome-locale.ts)
            'clearSearchField' => __('Clear search field', 'typesense-search'),

            // pagination.ts – nav aria-label
            'paginationLabel'  => __('Search result pages', 'typesense-search'),
            'paginationPrevious' => __('Previous page', 'typesense-search'),
            'paginationNext'     => __('Next page', 'typesense-search'),

            // search page loader (role="status")
            'loadingSearch'    => __('Loading search…', 'typesense-search'),
        ];
    }

    /**
     * Returns translatable strings for the quick-search JS bundle.
     * Localized as `window.typesenseQuickSearchI18n` on every page.
     *
     * @return array<string, string>
     */
    public static function quickSearchStrings(): array
    {
        return [
            'seeAllResults' => __('See all results', 'typesense-search'),
            'noResults'     => __('No hits for your search term', 'typesense-search'),
            'dialogTitle'   => __('Quick search', 'typesense-search'),
            'dialogHint'    => __('Search suggestions open in this dialog. Press Escape or use the close button to return to the page.', 'typesense-search'),
            'closeDialog'   => __('Close quick search', 'typesense-search'),
            'searchLabel'   => __('Search', 'typesense-search'),
            'submitSearch'  => __('Search', 'typesense-search'),
        ];
    }

    /**
     * Returns an array of all translatable strings for the admin settings JS bundle.
     *
     * @return array<string, string>
     */
    public static function adminStrings(): array
    {
        return [
            // Generic feedback
            'ok'                   => __('OK', 'typesense-search'),
            'unknownError'         => __('Unknown error.', 'typesense-search'),
            'error'                => __('Error.', 'typesense-search'),
            // %s is replaced with the error message in JS
            'requestFailed'        => __('Request failed: ', 'typesense-search'),

            // Statistics – donut chart
            'pieChartLabel'        => __('Pie chart showing document distribution', 'typesense-search'),
            'documentsLabel'       => __('documents', 'typesense-search'),

            // Statistics – post-type row
            // %s is replaced with the post type label in JS
            'confirmClearType'     => __('Remove all "%s" documents from the Typesense index? This cannot be undone.', 'typesense-search'),
            'clearBtn'             => __('Clear', 'typesense-search'),
            // %s is replaced with the post type label in JS
            'confirmReindexType'   => __('Re-index all "%s" documents? Existing entries will be re-processed and overwritten.', 'typesense-search'),
            'reindexBtn'           => __('Reindex', 'typesense-search'),
            'reindexExternalTitle' => __('Managed by an external strategy — use WP-CLI to reindex.', 'typesense-search'),
            'externalBadge'        => __('External', 'typesense-search'),

            // Logging
            'confirmClearLog'      => __('Clear the indexing log?', 'typesense-search'),

            // Facetting – notices
            'couldNotLoadFields'   => __('Could not load facetable fields.', 'typesense-search'),
            'noFacetableFields'    => __('— No facetable fields found —', 'typesense-search'),
            'noFieldsInSchema'     => __('No facetable fields found in the collection schema. Make sure the collection exists and has fields with facet: true.', 'typesense-search'),
            'allFieldsAdded'       => __('All facetable fields have already been added as facets.', 'typesense-search'),

            // Facetting – row labels / placeholders
            'facetFieldLabel'      => __('Field', 'typesense-search'),
            'facetLabelLabel'      => __('Label', 'typesense-search'),
            'facetLabelPlaceholder' => __('e.g. Category', 'typesense-search'),
            'facetPlaceholderLabel' => __('Placeholder', 'typesense-search'),
            'facetPlaceholderPh'   => __('e.g. All categories', 'typesense-search'),
            'facetDisplayAsLabel'  => __('Display as', 'typesense-search'),
            'facetOptDropdown'     => __('Dropdown', 'typesense-search'),
            'facetOptButtonGroup'  => __('Button group', 'typesense-search'),
            'removeFacet'          => __('Remove facet', 'typesense-search'),

            // Quick search tab
            'qsSelectorLabel'      => __('CSS selector', 'typesense-search'),
            'qsSelectorPlaceholder' => __('e.g. .site-header input[type=search]', 'typesense-search'),
            'removeSelector'       => __('Remove selector', 'typesense-search'),
            'qsPlacementLabel'     => __('Placement', 'typesense-search'),
            'qsPlacementDefault'   => __('Default (body)', 'typesense-search'),
            'qsPlacementSibling'   => __('Sibling', 'typesense-search'),
            'qsMobileBehaviorLabel' => __('Mobile behavior', 'typesense-search'),
            'qsMobileBehaviorRegular' => __('Regular behavior', 'typesense-search'),
            'qsMobileBehaviorOverlay' => __('Open in modal', 'typesense-search'),
            'qsNoSelectors'        => __('No selectors configured yet. Click "Add selector" to get started.', 'typesense-search'),
        ];
    }

    /**
     * Returns translatable strings for the pinned-results admin app.
     *
     * @return array<string, string>
     */
    public static function pinnedResultsStrings(): array
    {
        return [
            'missingConfig'              => __('Missing pinned results configuration.', 'typesense-search'),
            'loadingPinnedSearches'      => __('Loading pinned searches...', 'typesense-search'),
            'couldNotLoadPinnedResults'  => __('Could not load pinned results.', 'typesense-search'),
            'pinnedSearchResults'        => __('Pinned search results', 'typesense-search'),
            'addPinnedSearch'            => __('Add pinned search', 'typesense-search'),
            'syncToTypesense'            => __('Sync to Typesense', 'typesense-search'),
            'syncing'                    => __('Syncing...', 'typesense-search'),
            'searchPinnedSearches'       => __('Search pinned searches...', 'typesense-search'),
            'noPinnedSearches'           => __('No pinned searches yet.', 'typesense-search'),
            'noFilteredPinnedSearches'   => __('No pinned searches match your filter.', 'typesense-search'),
            'searchPhrase'               => __('Search phrase', 'typesense-search'),
            'matchType'                  => __('Match type', 'typesense-search'),
            'pinnedResults'              => __('Pinned results', 'typesense-search'),
            'syncStatus'                 => __('Sync status', 'typesense-search'),
            'itemSingular'               => __('item', 'typesense-search'),
            'itemPlural'                 => __('items', 'typesense-search'),
            'newStatus'                  => __('New', 'typesense-search'),
            'syncedStatus'               => __('Synced', 'typesense-search'),
            'errorStatus'                => __('Error', 'typesense-search'),
            'pendingStatus'              => __('Pending', 'typesense-search'),
            'disabledStatus'             => __('Disabled', 'typesense-search'),
            'exact'                      => __('Exact', 'typesense-search'),
            'contains'                   => __('Contains', 'typesense-search'),
            'newPinnedSearch'            => __('New pinned search', 'typesense-search'),
            'pinnedSearch'               => __('Pinned search', 'typesense-search'),
            'enabled'                    => __('Enabled', 'typesense-search'),
            'searchPostsToAdd'           => __('Search posts to add...', 'typesense-search'),
            'searching'                  => __('Searching...', 'typesense-search'),
            'noResultsFound'             => __('No results found.', 'typesense-search'),
            'dragToReorder'              => __('Drag to reorder', 'typesense-search'),
            'removeResult'               => __('Remove result', 'typesense-search'),
            'emptyPinnedResultsHelp'     => __('Search for posts below and add the results you want to pin.', 'typesense-search'),
            'saveChanges'                => __('Save changes', 'typesense-search'),
            'saving'                     => __('Saving...', 'typesense-search'),
            'saved'                      => __('Saved', 'typesense-search'),
            'deletePinnedSearch'         => __('Delete pinned search', 'typesense-search'),
            'confirmDeletePinnedSearch'  => __('Delete this pinned search?', 'typesense-search'),
            'emptyPhraseError'           => __('Add a search phrase before saving.', 'typesense-search'),
            'emptyPinnedResultsError'    => __('Add at least one pinned result before saving.', 'typesense-search'),
            'savedNotice'                => __('Pinned search saved. Sync to Typesense when you are ready.', 'typesense-search'),
            'saveError'                  => __('Could not save pinned search.', 'typesense-search'),
            'deletedNotice'              => __('Pinned search deleted. Sync to Typesense to apply the change.', 'typesense-search'),
            'deleteError'                => __('Could not delete pinned search.', 'typesense-search'),
            'syncSuccess'                => __('Pinned searches synced.', 'typesense-search'),
            'syncError'                  => __('Could not sync pinned searches.', 'typesense-search'),
            'postSearchError'            => __('Could not search posts.', 'typesense-search'),
        ];
    }
}
