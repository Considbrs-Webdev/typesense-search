<?php
if ($activeTab !== 'status') {
    return;
}
?>

<div class="ts-settings__panel" id="ts-tab-status">

    <div class="ts-settings__card ts-status-card">
        <div class="ts-settings__card-header">
            <h2><?php esc_html_e('Connection &amp; key status', 'typesense-search'); ?></h2>
            <p><?php esc_html_e('Live checks against your saved Typesense settings. Use this to quickly diagnose connection or key problems.', 'typesense-search'); ?></p>
        </div>

        <!-- Loading state -->
        <div id="ts-status-loading" class="ts-stats-loading">
            <svg class="ts-stats-loading__spinner" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
            <span><?php esc_html_e('Running checks…', 'typesense-search'); ?></span>
        </div>

        <!-- Results -->
        <div id="ts-status-results" hidden>
            <ul class="ts-status-list">

                <!-- Connection -->
                <li class="ts-status-item" id="ts-status-connection">
                    <span class="ts-status-item__icon" aria-hidden="true"></span>
                    <div class="ts-status-item__body">
                        <strong class="ts-status-item__label"><?php esc_html_e('Server connection', 'typesense-search'); ?></strong>
                        <span class="ts-status-item__message"></span>
                    </div>
                </li>

                <!-- Collection -->
                <li class="ts-status-item" id="ts-status-collection">
                    <span class="ts-status-item__icon" aria-hidden="true"></span>
                    <div class="ts-status-item__body">
                        <strong class="ts-status-item__label"><?php esc_html_e('Collection', 'typesense-search'); ?></strong>
                        <span class="ts-status-item__message"></span>

                        <!-- Shown when the collection is missing but admin key is valid -->
                        <div id="ts-status-create-col-wrap" hidden>
                            <p class="ts-status-fix__description">
                                <?php esc_html_e('The collection does not exist yet. It can be created automatically using your saved admin key.', 'typesense-search'); ?>
                            </p>
                            <button type="button" id="ts-status-create-col" class="button button-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                <?php esc_html_e('Create collection', 'typesense-search'); ?>
                            </button>
                            <span id="ts-status-create-col-result" class="ts-status-fix__result" hidden></span>
                        </div>
                    </div>
                </li>

                <!-- Admin key -->
                <li class="ts-status-item" id="ts-status-admin-key">
                    <span class="ts-status-item__icon" aria-hidden="true"></span>
                    <div class="ts-status-item__body">
                        <strong class="ts-status-item__label"><?php esc_html_e('Admin key', 'typesense-search'); ?></strong>
                        <span class="ts-status-item__message"></span>
                    </div>
                </li>

                <!-- Search key -->
                <li class="ts-status-item" id="ts-status-search-key">
                    <span class="ts-status-item__icon" aria-hidden="true"></span>
                    <div class="ts-status-item__body">
                        <strong class="ts-status-item__label"><?php esc_html_e('Search key', 'typesense-search'); ?></strong>
                        <span class="ts-status-item__message"></span>

                        <!-- Shown when the search key can be fixed automatically -->
                        <div id="ts-status-fix-wrap" hidden>
                            <p class="ts-status-fix__description">
                                <?php esc_html_e('A new search key scoped to the configured collection will be created and saved automatically. If an existing key has the wrong collection scope, Typesense does not allow changing it — a fresh key will be created instead.', 'typesense-search'); ?>
                            </p>
                            <button type="button" id="ts-status-fix-key" class="button button-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                                <?php esc_html_e('Create new search key for this collection', 'typesense-search'); ?>
                            </button>
                            <span id="ts-status-fix-result" class="ts-status-fix__result" hidden></span>
                        </div>

                        <!-- Shown when fix is not possible (admin key invalid / no options set) -->
                        <p id="ts-status-regen-hint" class="ts-status-fix__hint" hidden>
                            <?php esc_html_e('Please go to the Connection tab, enter a valid admin key, and click "Generate search key" to create a new one.', 'typesense-search'); ?>
                        </p>
                    </div>
                </li>

            </ul>
        </div>

        <!-- Action bar -->
        <div class="ts-status-actions">
            <button type="button" id="ts-status-refresh" class="button">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                <?php esc_html_e('Re-run checks', 'typesense-search'); ?>
            </button>
        </div>

    </div><!-- .ts-status-card -->

</div><!-- #ts-tab-status -->
