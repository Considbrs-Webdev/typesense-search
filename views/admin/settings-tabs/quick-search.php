<?php
use TypesenseSearch\Admin\Settings;

if ($activeTab !== 'quick-search') {
    return;
}
?>

<div class="ts-settings__panel" id="ts-tab-quick-search">
    <form method="post" action="options.php">
        <?php settings_fields(Settings::OPTION_GROUP_QUICK_SEARCH); ?>

        <div class="ts-settings__card">
            <div class="ts-settings__card-header">
                <h2><?php esc_html_e('Quick search', 'typesense-search'); ?></h2>
                <p><?php esc_html_e('Enable autocomplete / quick search for input fields outside the main search page. When active, users get instant suggestions while typing in any matched field.', 'typesense-search'); ?></p>
            </div>

            <div class="ts-settings__fields">

                <!-- Enable / disable toggle -->
                <div class="ts-field">
                    <div class="ts-field__label">
                        <?php esc_html_e('Enable quick search', 'typesense-search'); ?>
                    </div>
                    <div class="ts-field__body">
                        <input type="hidden" name="<?php echo esc_attr(Settings::OPTION_QUICK_SEARCH_ENABLED); ?>" value="0" />

                        <label for="ts-quick-search-enabled" class="ts-toggle">
                            <input
                                type="checkbox"
                                id="ts-quick-search-enabled"
                                name="<?php echo esc_attr(Settings::OPTION_QUICK_SEARCH_ENABLED); ?>"
                                value="1"
                                <?php checked(1, $quickSearchEnabled); ?>
                                class="ts-toggle__input"
                            />
                            <span class="ts-toggle__track" aria-hidden="true">
                                <span class="ts-toggle__thumb"></span>
                            </span>
                            <span class="ts-toggle__status">
                                <span class="ts-toggle__status-on"><?php esc_html_e('On', 'typesense-search'); ?></span>
                                <span class="ts-toggle__status-off"><?php esc_html_e('Off', 'typesense-search'); ?></span>
                            </span>
                        </label>

                        <p class="ts-field__description">
                            <?php esc_html_e('When enabled, quick search will be attached to the CSS selectors defined below. It reuses the same frontend Typesense configuration as the main search.', 'typesense-search'); ?>
                        </p>
                    </div>
                </div>

                <!-- Number of hits — only visible when quick search is enabled -->
                <div
                    id="ts-quick-search-hits-field"
                    class="ts-field"
                    <?php echo $quickSearchEnabled ? '' : 'hidden'; ?>
                >
                    <div class="ts-field__label">
                        <?php esc_html_e('Number of results', 'typesense-search'); ?>
                    </div>
                    <div class="ts-field__body">
                        <input
                            type="number"
                            id="ts-quick-search-hits-per-page"
                            name="<?php echo esc_attr(Settings::OPTION_QUICK_SEARCH_HITS_PER_PAGE); ?>"
                            value="<?php echo esc_attr($quickSearchHitsPerPage); ?>"
                            min="1"
                            max="50"
                            class="small-text"
                        />
                        <p class="ts-field__description">
                            <?php esc_html_e('How many suggestions to show in the quick search dropdown.', 'typesense-search'); ?>
                        </p>
                    </div>
                </div>

            </div>
        </div>

        <!-- CSS selectors card — only visible when quick search is enabled -->
        <div
            id="ts-quick-search-selectors-card"
            class="ts-settings__card"
            <?php echo $quickSearchEnabled ? '' : 'hidden'; ?>
        >
            <div class="ts-settings__card-header">
                <h2><?php esc_html_e('Target fields', 'typesense-search'); ?></h2>
                <p><?php esc_html_e('Add one or more CSS selectors that match the search inputs you want to enhance with quick search autocomplete.', 'typesense-search'); ?></p>
            </div>

            <!-- Selector rows -->
            <div id="ts-qs-selector-list" class="ts-repeater-list">
                <?php if (!empty($quickSearchSelectors)) : ?>
                    <?php foreach ($quickSearchSelectors as $index => $entry) : ?>
                    <div class="ts-qs-selector-row" data-index="<?php echo esc_attr($index); ?>">
                        <div class="ts-qs-selector-row__field" style="flex:1">
                            <label class="ts-qs-selector-row__label"><?php esc_html_e('CSS selector', 'typesense-search'); ?></label>
                            <input
                                type="text"
                                name="<?php echo esc_attr(Settings::OPTION_QUICK_SEARCH_SELECTORS); ?>[<?php echo esc_attr($index); ?>][selector]"
                                value="<?php echo esc_attr($entry['selector']); ?>"
                                placeholder="<?php esc_attr_e('e.g. .site-header input[type=search]', 'typesense-search'); ?>"
                                class="regular-text ts-qs-selector-row__input"
                                spellcheck="false"
                            />
                        </div>
                        <div class="ts-qs-selector-row__field">
                            <label class="ts-qs-selector-row__label"><?php esc_html_e('Placement', 'typesense-search'); ?></label>
                            <?php $isSibling = !empty($entry['sibling']); ?>
                            <select
                                name="<?php echo esc_attr(Settings::OPTION_QUICK_SEARCH_SELECTORS); ?>[<?php echo esc_attr($index); ?>][sibling]"
                                class="ts-qs-selector-row__sibling-select"
                            >
                                <option value="0" <?php selected($isSibling, false); ?>><?php esc_html_e('Default (body)', 'typesense-search'); ?></option>
                                <option value="1" <?php selected($isSibling, true); ?>><?php esc_html_e('Sibling', 'typesense-search'); ?></option>
                            </select>
                        </div>
                        <div class="ts-qs-selector-row__field">
                            <label class="ts-qs-selector-row__label" for="ts-qs-mobile-behavior-<?php echo esc_attr($index); ?>"><?php esc_html_e('Mobile behavior', 'typesense-search'); ?></label>
                            <?php $mobileBehavior = (($entry['mobile_behavior'] ?? '') === 'overlay' || !empty($entry['mobile_overlay'])) ? 'overlay' : 'regular'; ?>
                            <select
                                id="ts-qs-mobile-behavior-<?php echo esc_attr($index); ?>"
                                name="<?php echo esc_attr(Settings::OPTION_QUICK_SEARCH_SELECTORS); ?>[<?php echo esc_attr($index); ?>][mobile_behavior]"
                                class="ts-qs-selector-row__mobile-behavior-select"
                            >
                                <option value="regular" <?php selected($mobileBehavior, 'regular'); ?>><?php esc_html_e('Regular behavior', 'typesense-search'); ?></option>
                                <option value="overlay" <?php selected($mobileBehavior, 'overlay'); ?>><?php esc_html_e('Open in modal', 'typesense-search'); ?></option>
                            </select>
                        </div>
                        <button
                            type="button"
                            class="button ts-qs-selector-row__remove"
                            title="<?php esc_attr_e('Remove selector', 'typesense-search'); ?>"
                            aria-label="<?php esc_attr_e('Remove selector', 'typesense-search'); ?>"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Empty state -->
            <p id="ts-qs-selector-empty" class="ts-settings__empty" <?php echo !empty($quickSearchSelectors) ? 'hidden' : ''; ?>>
                <?php esc_html_e('No selectors configured yet. Click "Add selector" to get started.', 'typesense-search'); ?>
            </p>

            <!-- Add selector button -->
            <div class="ts-repeater-actions">
                <button type="button" id="ts-qs-add-selector" class="button button-secondary ts-connection-test__button">
                    <svg class="ts-connection-test__icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?php esc_html_e('Add selector', 'typesense-search'); ?>
                </button>
            </div>
        </div>

        <?php submit_button(__('Save settings', 'typesense-search'), 'primary ts-settings__submit'); ?>
    </form>
</div>

<?php
// End quick search tab
?>
