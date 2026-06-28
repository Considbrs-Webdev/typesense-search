<?php

namespace TypesenseSearch\Admin;

use TypesenseSearch\Admin\Ajax\CollectionActions;
use TypesenseSearch\Admin\Ajax\ConnectionActions;
use TypesenseSearch\Admin\Ajax\FacetActions;
use TypesenseSearch\Admin\Ajax\IndexingActions;
use TypesenseSearch\Admin\Ajax\LogActions;
use TypesenseSearch\Admin\Ajax\SearchKeyActions;

/**
 * Class SettingsAjax
 *
 * Registration entry point for the admin-ajax.php endpoints that back the
 * Typesense settings page. Each handler group lives in its own action class
 * under TypesenseSearch\Admin\Ajax; this class wires them to WordPress.
 *
 * AJAX action name constants are kept here so that action classes, JavaScript,
 * and any third-party code can reference them from a single canonical location.
 *
 * @package TypesenseSearch\Admin
 */
class SettingsAjax
{
    public const AJAX_ACTION_TEST              = 'typesense_test_connection';
    public const AJAX_ACTION_CREATE_COL        = 'typesense_create_collection';
    public const AJAX_ACTION_GEN_KEY           = 'typesense_generate_search_key';
    public const AJAX_ACTION_GET_STATS         = 'typesense_get_stats';
    public const AJAX_ACTION_CLEAR_POST_TYPE   = 'typesense_clear_post_type';
    public const AJAX_ACTION_REINDEX_POST_TYPE = 'typesense_reindex_post_type';
    public const AJAX_ACTION_GET_FACET_FIELDS  = 'typesense_get_facet_fields';
    public const AJAX_ACTION_CHECK_STATUS      = 'typesense_check_status';
    public const AJAX_ACTION_FIX_SEARCH_KEY    = 'typesense_fix_search_key';
    public const AJAX_ACTION_STATUS_CREATE_COL = 'typesense_status_create_collection';
    public const AJAX_ACTION_CLEAR_LOG         = 'typesense_clear_indexing_log';

    public function __construct()
    {
        (new ConnectionActions())->register();
        (new CollectionActions())->register();
        (new SearchKeyActions())->register();
        (new IndexingActions())->register();
        (new FacetActions())->register();
        (new LogActions())->register();
    }
}
