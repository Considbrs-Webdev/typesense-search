<?php
use TypesenseSearch\Admin\Settings;

if ($activeTab !== 'advanced-settings') {
    return;
}
?>

<div class="ts-settings__panel" id="ts-tab-advanced-settings">
    <form method="post" action="options.php">
        <?php settings_fields(Settings::OPTION_GROUP_ADVANCED_SETTINGS); ?>

        <?php typesense_search_render_search_result_behavior(); ?>

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

        <div class="ts-settings__card">
            <div class="ts-settings__card-header">
                <h2><?php esc_html_e('Search field weights', 'typesense-search'); ?></h2>
                <p><?php esc_html_e('Control how much each search field contributes to result relevance.', 'typesense-search'); ?></p>
            </div>

            <div class="ts-settings__fields">
                <?php
                $queryByWeights = array_merge(
                    Settings::getDefaultQueryByWeights(),
                    (array) get_option(Settings::OPTION_QUERY_BY_WEIGHTS, [])
                );
                ?>
                <?php foreach (Settings::getSearchWeightFields() as $field => $label) : ?>
                <div class="ts-field">
                    <div class="ts-field__label"><?php echo esc_html($label); ?></div>
                    <div class="ts-field__body">
                        <div class="ts-weight-scale" role="radiogroup" aria-label="<?php echo esc_attr($label); ?>">
                            <?php for ($weight = 1; $weight <= 5; $weight++) : ?>
                            <?php $inputId = sprintf('ts-query-weight-%s-%d', $field, $weight); ?>
                            <label class="ts-weight-scale__option" for="<?php echo esc_attr($inputId); ?>">
                                <input
                                    type="radio"
                                    id="<?php echo esc_attr($inputId); ?>"
                                    name="<?php echo esc_attr(Settings::OPTION_QUERY_BY_WEIGHTS); ?>[<?php echo esc_attr($field); ?>]"
                                    value="<?php echo esc_attr($weight); ?>"
                                    <?php checked($weight, (int) ($queryByWeights[$field] ?? 1)); ?>
                                    class="ts-weight-scale__input"
                                />
                                <span class="ts-weight-scale__label"><?php echo esc_html((string) $weight); ?></span>
                            </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php function typesense_search_render_search_result_behavior(): void { ?>
        <div class="ts-settings__card">
            <div class="ts-settings__card-header">
                <h2><?php esc_html_e('Search result behavior', 'typesense-search'); ?></h2>
                <p><?php esc_html_e('Fine-tune highlighting, snippet truncation, and the timing of search requests.', 'typesense-search'); ?></p>
            </div>

            <div class="ts-settings__fields">
                <div class="ts-field">
                    <label for="ts-highlight-affix-num-tokens" class="ts-field__label">
                        <?php esc_html_e('Highlight context tokens', 'typesense-search'); ?>
                    </label>
                    <div class="ts-field__body">
                        <input
                            type="number"
                            id="ts-highlight-affix-num-tokens"
                            name="<?php echo esc_attr(Settings::OPTION_HIGHLIGHT_AFFIX_NUM_TOKENS); ?>"
                            value="<?php echo esc_attr(get_option(Settings::OPTION_HIGHLIGHT_AFFIX_NUM_TOKENS, 15)); ?>"
                            class="small-text ts-field__input"
                            step="1"
                            min="1"
                            max="50"
                        />
                        <p class="ts-field__description">
                            <?php esc_html_e('Number of tokens (words) to include before and after each highlighted match in search result snippets. Higher values show more context around matches.', 'typesense-search'); ?>
                        </p>
                    </div>
                </div>

                <div class="ts-field">
                    <label for="ts-truncator-mode" class="ts-field__label">
                        <?php esc_html_e('Snippet truncator', 'typesense-search'); ?>
                    </label>
                    <div class="ts-field__body">
                        <?php
                        $current = get_option(Settings::OPTION_TRUNCATOR, '[...]');
                        $preBrackets = '[...]';
                        $preEllipsis = '…';
                        $preNone = 'none';
                        $mode = ($current === $preBrackets) ? 'brackets' : (($current === $preEllipsis) ? 'ellipsis' : (($current === $preNone) ? 'none' : 'custom'));
                        ?>
                        <select id="ts-truncator-mode" class="ts-field__input">
                            <option value="brackets" <?php selected('brackets', $mode); ?>><?php esc_html_e('Brackets (e.g. [...] )', 'typesense-search'); ?></option>
                            <option value="ellipsis" <?php selected('ellipsis', $mode); ?>><?php esc_html_e('Ellipsis (e.g. … )', 'typesense-search'); ?></option>
                            <option value="none" <?php selected('none', $mode); ?>><?php esc_html_e('None (no truncation marker)', 'typesense-search'); ?></option>
                            <option value="custom" <?php selected('custom', $mode); ?>><?php esc_html_e('Custom', 'typesense-search'); ?></option>
                        </select>
                        <input type="hidden" id="ts-truncator" name="<?php echo esc_attr(Settings::OPTION_TRUNCATOR); ?>" value="<?php echo esc_attr($current); ?>" />
                        <input
                            type="text"
                            id="ts-truncator-custom"
                            class="regular-text ts-field__input"
                            placeholder="[...]"
                            value="<?php echo esc_attr($mode === 'custom' ? $current : ''); ?>"
                            <?php echo $mode === 'custom' ? '' : 'hidden'; ?>
                        />
                        <p class="ts-field__description">
                            <?php esc_html_e("Select any of the predefined markers, or choose 'Custom' to enter your own.", 'typesense-search'); ?>
                        </p>
                    </div>
                </div>

                <div class="ts-field">
                    <div class="ts-field__label"><?php esc_html_e('Debounce search', 'typesense-search'); ?></div>
                    <div class="ts-field__body">
                        <input type="hidden" name="<?php echo esc_attr(Settings::OPTION_DEBOUNCE); ?>" value="0" />
                        <label for="ts-debounce" class="ts-toggle">
                            <input
                                type="checkbox"
                                id="ts-debounce"
                                name="<?php echo esc_attr(Settings::OPTION_DEBOUNCE); ?>"
                                value="1"
                                <?php checked(1, (int) get_option(Settings::OPTION_DEBOUNCE, 1)); ?>
                                class="ts-toggle__input"
                            />
                            <span class="ts-toggle__track" aria-hidden="true"><span class="ts-toggle__thumb"></span></span>
                            <span class="ts-toggle__status">
                                <span class="ts-toggle__status-on"><?php esc_html_e('On', 'typesense-search'); ?></span>
                                <span class="ts-toggle__status-off"><?php esc_html_e('Off', 'typesense-search'); ?></span>
                            </span>
                        </label>
                        <p class="ts-field__description">
                            <?php esc_html_e('When enabled, search results are fetched only after the user pauses typing (debounced). When disabled, results appear immediately on every keystroke.', 'typesense-search'); ?>
                        </p>
                    </div>
                </div>

                <div class="ts-field" id="ts-debounce-delay-field"<?php echo (int) get_option(Settings::OPTION_DEBOUNCE, 1) ? '' : ' hidden'; ?>>
                    <label for="ts-debounce-delay" class="ts-field__label">
                        <?php esc_html_e('Debounce delay (ms)', 'typesense-search'); ?>
                    </label>
                    <div class="ts-field__body">
                        <input
                            type="number"
                            id="ts-debounce-delay"
                            name="<?php echo esc_attr(Settings::OPTION_DEBOUNCE_DELAY); ?>"
                            value="<?php echo esc_attr(get_option(Settings::OPTION_DEBOUNCE_DELAY, 300)); ?>"
                            class="small-text ts-field__input"
                            step="50"
                            min="50"
                            max="2000"
                        />
                        <p class="ts-field__description">
                            <?php esc_html_e('How long to wait after the user stops typing before firing the search request.', 'typesense-search'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>

        <script>
        (function () {
            var mode = document.getElementById('ts-truncator-mode');
            var hidden = document.getElementById('ts-truncator');
            var custom = document.getElementById('ts-truncator-custom');
            var debounce = document.getElementById('ts-debounce');
            var delayField = document.getElementById('ts-debounce-delay-field');
            var predefined = { brackets: '[...]', ellipsis: '…', none: 'none' };

            if (mode && hidden && custom) {
                function updateTruncator() {
                    if (mode.value === 'custom') {
                        custom.hidden = false;
                        hidden.value = custom.value || hidden.value || predefined.brackets;
                    } else {
                        custom.hidden = true;
                        hidden.value = predefined[mode.value] || predefined.brackets;
                    }
                }
                mode.addEventListener('change', updateTruncator);
                custom.addEventListener('input', function () { hidden.value = custom.value || predefined.brackets; });
                updateTruncator();
            }

            if (debounce && delayField) {
                debounce.addEventListener('change', function () { delayField.hidden = !this.checked; });
            }
        }());
        </script>

        <?php submit_button(__('Save settings', 'typesense-search'), 'primary ts-settings__submit'); ?>
    </form>
</div>

<?php
// End advanced settings tab
?>
