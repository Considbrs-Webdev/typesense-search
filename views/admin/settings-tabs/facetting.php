<?php
use TypesenseSearch\Admin\Settings;

if ($activeTab !== 'facetting') {
    return;
}
?>

<div class="ts-settings__panel" id="ts-tab-facetting">
    <form method="post" action="options.php">
        <?php settings_fields(Settings::OPTION_GROUP_FACETS); ?>

        <div class="ts-settings__card">
            <div class="ts-settings__card-header">
                <h2><?php esc_html_e('Facets', 'typesense-search'); ?></h2>
                <p><?php esc_html_e('Define which fields can be used to filter search results. Each facet maps a Typesense field to a human-readable label and an optional placeholder shown in the UI.', 'typesense-search'); ?></p>
            </div>

            <!-- Loading / error notice for field fetch -->
            <div id="ts-facet-notice" class="ts-facet-notice" hidden>
                <span class="ts-facet-notice__message"></span>
            </div>

            <!-- Facet rows -->
            <div id="ts-facet-list" class="ts-repeater-list">
                <?php if (!empty($facets)) : ?>
                    <?php foreach ($facets as $index => $facet) : ?>
                    <div class="ts-facet-row" data-index="<?php echo esc_attr($index); ?>">
                        <div class="ts-facet-row__field">
                            <label class="ts-facet-row__label"><?php esc_html_e('Field', 'typesense-search'); ?></label>
                            <div class="ts-facet-row__select-wrap">
                                <select
                                    name="<?php echo esc_attr(Settings::OPTION_FACETS); ?>[<?php echo esc_attr($index); ?>][field]"
                                    class="ts-facet-row__select"
                                    data-saved-value="<?php echo esc_attr($facet['field']); ?>"
                                    disabled
                                >
                                    <option value="<?php echo esc_attr($facet['field']); ?>" selected>
                                        <?php echo esc_html($facet['field']); ?>
                                    </option>
                                </select>
                                <svg class="ts-facet-row__spinner" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                            </div>
                        </div>
                        <div class="ts-facet-row__field">
                            <label class="ts-facet-row__label"><?php esc_html_e('Label', 'typesense-search'); ?></label>
                            <input
                                type="text"
                                name="<?php echo esc_attr(Settings::OPTION_FACETS); ?>[<?php echo esc_attr($index); ?>][label]"
                                value="<?php echo esc_attr($facet['label']); ?>"
                                placeholder="<?php esc_attr_e('e.g. Category', 'typesense-search'); ?>"
                                class="regular-text ts-facet-row__input"
                            />
                        </div>
                        <div class="ts-facet-row__field">
                            <label class="ts-facet-row__label"><?php esc_html_e('Placeholder', 'typesense-search'); ?></label>
                            <input
                                type="text"
                                name="<?php echo esc_attr(Settings::OPTION_FACETS); ?>[<?php echo esc_attr($index); ?>][placeholder]"
                                value="<?php echo esc_attr($facet['placeholder']); ?>"
                                placeholder="<?php esc_attr_e('e.g. All categories', 'typesense-search'); ?>"
                                class="regular-text ts-facet-row__input"
                            />
                        </div>
                            <div class="ts-facet-row__field">
                                <label class="ts-facet-row__label"><?php esc_html_e('Display as', 'typesense-search'); ?></label>
                                <select
                                    name="<?php echo esc_attr(Settings::OPTION_FACETS); ?>[<?php echo esc_attr($index); ?>][display_as]"
                                    class="ts-facet-row__display"
                                    data-saved-display="<?php echo esc_attr($facet['display_as'] ?? 'dropdown'); ?>"
                                    disabled
                                >
                                    <option value="dropdown" <?php selected(($facet['display_as'] ?? 'dropdown'), 'dropdown'); ?>><?php esc_html_e('Dropdown', 'typesense-search'); ?></option>
                                    <option value="button_group" <?php selected(($facet['display_as'] ?? 'dropdown'), 'button_group'); ?>><?php esc_html_e('Button group', 'typesense-search'); ?></option>
                                </select>
                            </div>
                        <button
                            type="button"
                            class="button ts-facet-row__remove"
                            title="<?php esc_attr_e('Remove facet', 'typesense-search'); ?>"
                            aria-label="<?php esc_attr_e('Remove facet', 'typesense-search'); ?>"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Empty state -->
            <p id="ts-facet-empty" class="ts-settings__empty" <?php echo !empty($facets) ? 'hidden' : ''; ?>>
                <?php esc_html_e('No facets configured yet. Click "Add facet" to get started.', 'typesense-search'); ?>
            </p>

            <!-- Add facet button -->
            <div class="ts-repeater-actions">
                <button type="button" id="ts-add-facet" class="button button-secondary ts-connection-test__button">
                    <svg class="ts-connection-test__spinner" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                    <svg class="ts-connection-test__icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?php esc_html_e('Add facet', 'typesense-search'); ?>
                </button>
            </div>
        </div>

        <?php submit_button(__('Save settings', 'typesense-search'), 'primary ts-settings__submit'); ?>
    </form>
</div>

<?php
// End faceting tab
?>
