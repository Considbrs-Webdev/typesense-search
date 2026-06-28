<?php

namespace TypesenseSearch\Admin\Settings;

/**
 * All WordPress option keys and option groups used by the plugin.
 *
 * @package TypesenseSearch\Admin\Settings
 */
class OptionKeys
{
    public const PAGE_SLUG = 'typesense-search';

    public const OPTION_GROUP_CONNECTION        = 'typesense_search_connection';
    public const OPTION_GROUP_CONTENT           = 'typesense_search_content';
    public const OPTION_GROUP_ADVANCED_SETTINGS = 'typesense_search_advanced_settings';
    public const OPTION_GROUP_QUICK_SEARCH      = 'typesense_search_quick_search';

    public const OPTION_REMOTE       = 'typesense_search_remote';
    public const OPTION_INDEX_NAME   = 'typesense_search_index_name';
    public const OPTION_ADMIN_KEY    = 'typesense_search_admin_key';
    public const OPTION_SEARCH_KEY   = 'typesense_search_search_key';
    public const OPTION_FRONTEND_HOST = 'typesense_search_frontend_host';
    public const OPTION_POST_TYPES   = 'typesense_search_post_types';
    public const OPTION_FACETS       = 'typesense_search_facets';
    public const OPTION_HITS_PER_PAGE           = 'typesense_search_hits_per_page';
    public const OPTION_INDEX_MODULARITY        = 'typesense_index_modularity_content';
    public const OPTION_DEBOUNCE               = 'typesense_search_debounce';
    public const OPTION_DEBOUNCE_DELAY          = 'typesense_search_debounce_delay';
    public const OPTION_HIGHLIGHT_AFFIX_NUM_TOKENS = 'typesense_search_highlight_affix_num_tokens';
    public const OPTION_TRUNCATOR              = 'typesense_search_truncator';
    public const OPTION_SORT_DISPLAY           = 'typesense_search_sort_display';
    public const OPTION_QUERY_BY_WEIGHTS       = 'typesense_search_query_by_weights';
    public const OPTION_PINNED_RESULTS_ENABLED = 'typesense_search_pinned_results_enabled';

    public const OPTION_SEARCH_LOGGING_ENABLED             = 'typesense_search_logging_enabled';
    public const OPTION_SEARCH_LOGGING_DASHBOARD_WIDGETS   = 'typesense_search_logging_dashboard_widgets';
    public const OPTION_SEARCH_LOGGING_REQUIRE_CONSENT     = 'typesense_search_logging_require_consent';
    public const OPTION_SEARCH_LOGGING_DELAY_SECONDS       = 'typesense_search_logging_delay_seconds';
    public const OPTION_SEARCH_LOGGING_MINIMUM_CHARACTERS  = 'typesense_search_logging_minimum_characters';
    public const OPTION_SEARCH_STATISTICS_RETENTION_DAYS   = 'typesense_search_statistics_retention_days';

    public const OPTION_QUICK_SEARCH_ENABLED       = 'typesense_quick_search_enabled';
    public const OPTION_QUICK_SEARCH_SELECTORS     = 'typesense_quick_search_selectors';
    public const OPTION_QUICK_SEARCH_HITS_PER_PAGE = 'typesense_quick_search_hits_per_page';
    public const OPTION_INDEX_PDF                  = 'typesense_search_index_pdf';
}
