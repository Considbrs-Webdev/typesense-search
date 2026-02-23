<?php
if ($activeTab !== 'statistics') {
    return;
}
?>

<div class="ts-settings__panel" id="ts-tab-statistics">

    <div class="ts-settings__card ts-stats-card">
        <div class="ts-settings__card-header">
            <h2><?php esc_html_e('Index overview', 'typesense-search'); ?></h2>
            <p><?php esc_html_e('Live snapshot of what is currently stored in your Typesense collection. Use the clear buttons to remove all documents of a specific post type from the index.', 'typesense-search'); ?></p>
        </div>

        <!-- Loading state -->
        <div id="ts-stats-loading" class="ts-stats-loading">
            <svg class="ts-stats-loading__spinner" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
            <span><?php esc_html_e('Loading statistics…', 'typesense-search'); ?></span>
        </div>

        <!-- Error state -->
        <div id="ts-stats-error" class="ts-stats-error" hidden>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span id="ts-stats-error-message"></span>
        </div>

        <!-- Stats content -->
        <div id="ts-stats-content" hidden>

            <!-- Summary row -->
            <div class="ts-stats-summary">
                <div class="ts-stats-kpi">
                    <span class="ts-stats-kpi__value" id="ts-stats-total">—</span>
                    <span class="ts-stats-kpi__label"><?php esc_html_e('total documents', 'typesense-search'); ?></span>
                </div>
                <div class="ts-stats-kpi">
                    <span class="ts-stats-kpi__value" id="ts-stats-types">—</span>
                    <span class="ts-stats-kpi__label"><?php esc_html_e('post types indexed', 'typesense-search'); ?></span>
                </div>
                <div class="ts-stats-kpi">
                    <span class="ts-stats-kpi__value ts-stats-kpi__value--sm" id="ts-stats-collection">—</span>
                    <span class="ts-stats-kpi__label"><?php esc_html_e('collection', 'typesense-search'); ?></span>
                </div>
            </div>

            <!-- Chart + breakdown -->
            <div class="ts-stats-body">

                <!-- Donut pie chart -->
                <div class="ts-stats-chart" aria-hidden="true">
                    <div id="ts-pie-chart" class="ts-pie-chart"></div>
                </div>

                <!-- Per-type breakdown table -->
                <div class="ts-stats-breakdown">
                    <ul id="ts-stats-list" class="ts-stats-list"></ul>
                </div>

            </div>

        </div>
    </div>

    <!-- Refresh button -->
    <div class="ts-stats-footer">
        <button type="button" id="ts-stats-refresh" class="button button-secondary ts-connection-test__button">
            <svg class="ts-connection-test__spinner" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
            <svg class="ts-connection-test__icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>
            <?php esc_html_e('Refresh', 'typesense-search'); ?>
        </button>
    </div>

</div>

<?php
// End statistics tab
?>
