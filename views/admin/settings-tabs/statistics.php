<?php
if ($activeTab !== 'statistics') {
    return;
}
?>

<div class="ts-settings__panel" id="ts-tab-statistics">

    <div class="ts-settings__card ts-stats-card">
        <div class="ts-settings__card-header">
            <div class="ts-stats-card__header-text">
                <h2><?php esc_html_e('Index overview', 'typesense-search'); ?></h2>
                <p><?php esc_html_e('Live snapshot of what is currently stored in your Typesense collection. Use the clear buttons to remove all documents of a specific post type, or the reindex buttons to re-process and overwrite them.', 'typesense-search'); ?></p>
            </div>
            <button type="button" id="ts-stats-refresh" class="button button-secondary ts-connection-test__button">
                <svg class="ts-connection-test__spinner" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                <svg class="ts-connection-test__icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true"><path d="M552.2 64C538.9 64 528.2 74.7 528.2 88L528.2 166.1L501.1 139C401.1 39 239 39 139.1 139C39.2 239 39.1 401.1 139.1 501C239.1 600.9 401.2 601 501.1 501C516 486.1 528.7 469.8 539.2 452.5C546.1 441.2 542.4 426.4 531.1 419.5C519.8 412.6 505 416.3 498.1 427.6C489.6 441.6 479.3 454.9 467.1 467C385.9 548.2 254.2 548.2 172.9 467C91.6 385.8 91.7 254.1 172.9 172.8C254.1 91.5 385.8 91.6 467.1 172.8L494.2 199.9L416 199.9C402.7 199.9 392 210.6 392 223.9C392 237.2 402.7 247.9 416 247.9L552.1 247.9C565.4 247.9 576.1 237.2 576.1 223.9L576.1 87.9C576.1 74.6 565.4 63.9 552.1 63.9z"/></svg>
                <?php esc_html_e('Refresh', 'typesense-search'); ?>
            </button>

            <!-- Reindex / clear result notice -->
            <div id="ts-stats-notice" class="ts-stats-notice" hidden>
                <span id="ts-stats-notice-message" class="ts-stats-notice__message"></span>
                <button type="button" id="ts-stats-notice-dismiss" class="ts-stats-notice__dismiss" aria-label="<?php esc_attr_e('Dismiss', 'typesense-search'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
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



</div>

<?php
// End statistics tab
?>
