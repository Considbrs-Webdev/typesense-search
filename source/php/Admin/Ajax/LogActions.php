<?php

namespace TypesenseSearch\Admin\Ajax;

use TypesenseSearch\Admin\SettingsAjax;
use TypesenseSearch\Logger\IndexingLog;

/**
 * AJAX handler for clearing the indexing log.
 *
 * Covers:
 *   - typesense_clear_indexing_log (handleClearLog)
 *
 * @package TypesenseSearch\Admin\Ajax
 */
class LogActions
{
    use AjaxHelpers;

    public function register(): void
    {
        add_action('wp_ajax_' . SettingsAjax::AJAX_ACTION_CLEAR_LOG, [$this, 'handleClearLog']);
    }

    // ── 1. Clear indexing log ───────────────────────────────────────────────

    public function handleClearLog(): void
    {
        $this->requirePermission(SettingsAjax::AJAX_ACTION_CLEAR_LOG);

        IndexingLog::clear();

        wp_send_json_success([
            'message' => __('Indexing log cleared.', 'typesense-search'),
        ]);
    }
}
