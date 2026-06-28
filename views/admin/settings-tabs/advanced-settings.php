<?php
use TypesenseSearch\Admin\Settings;

if ($activeTab !== 'advanced-settings') {
    return;
}
?>

<div class="ts-settings__panel" id="ts-tab-advanced-settings">
    <form method="post" action="options.php">
        <?php settings_fields(Settings::OPTION_GROUP_ADVANCED_SETTINGS); ?>

        <?php include __DIR__ . '/advanced/search-result-behavior.php'; ?>

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


        <div class="ts-settings__card" id="ts-search-statistics-settings">
            <div class="ts-settings__card-header">
                <h2><?php esc_html_e('Search statistics', 'typesense-search'); ?></h2>
                <p><?php esc_html_e('Record completed searches in WordPress to identify content gaps and popular search terms. Each normalised term is stored only once per anonymous browser session.', 'typesense-search'); ?></p>
            </div>

            <div class="ts-settings__fields">
                <div class="ts-field">
                    <div class="ts-field__label"><?php esc_html_e('Enable search logging', 'typesense-search'); ?></div>
                    <div class="ts-field__body">
                        <input type="hidden" name="<?php echo esc_attr(Settings::OPTION_SEARCH_LOGGING_ENABLED); ?>" value="0" />
                        <label for="ts-search-logging-enabled" class="ts-toggle">
                            <input
                                type="checkbox"
                                id="ts-search-logging-enabled"
                                name="<?php echo esc_attr(Settings::OPTION_SEARCH_LOGGING_ENABLED); ?>"
                                value="1"
                                <?php checked(1, (int) get_option(Settings::OPTION_SEARCH_LOGGING_ENABLED, 0)); ?>
                                class="ts-toggle__input"
                            />
                            <span class="ts-toggle__track" aria-hidden="true"><span class="ts-toggle__thumb"></span></span>
                            <span class="ts-toggle__status"><span class="ts-toggle__status-on"><?php esc_html_e('On', 'typesense-search'); ?></span><span class="ts-toggle__status-off"><?php esc_html_e('Off', 'typesense-search'); ?></span></span>
                        </label>
                    </div>
                </div>

                <div class="ts-field">
                    <div class="ts-field__label"><?php esc_html_e('Dashboard widgets', 'typesense-search'); ?></div>
                    <div class="ts-field__body">
                        <input type="hidden" name="<?php echo esc_attr(Settings::OPTION_SEARCH_LOGGING_DASHBOARD_WIDGETS); ?>" value="0" />
                        <label for="ts-search-statistics-dashboard-widgets" class="ts-toggle">
                            <input
                                type="checkbox"
                                id="ts-search-statistics-dashboard-widgets"
                                name="<?php echo esc_attr(Settings::OPTION_SEARCH_LOGGING_DASHBOARD_WIDGETS); ?>"
                                value="1"
                                <?php checked(1, (int) get_option(Settings::OPTION_SEARCH_LOGGING_DASHBOARD_WIDGETS, 1)); ?>
                                class="ts-toggle__input"
                            />
                            <span class="ts-toggle__track" aria-hidden="true"><span class="ts-toggle__thumb"></span></span>
                            <span class="ts-toggle__status"><span class="ts-toggle__status-on"><?php esc_html_e('On', 'typesense-search'); ?></span><span class="ts-toggle__status-off"><?php esc_html_e('Off', 'typesense-search'); ?></span></span>
                        </label>
                        <p class="ts-field__description"><?php esc_html_e('Register Latest searches, Failed searches, and Popular searches widgets on the WordPress dashboard.', 'typesense-search'); ?></p>
                    </div>
                </div>

                <div class="ts-field">
                    <div class="ts-field__label"><?php esc_html_e('Require statistics consent', 'typesense-search'); ?></div>
                    <div class="ts-field__body">
                        <input type="hidden" name="<?php echo esc_attr(Settings::OPTION_SEARCH_LOGGING_REQUIRE_CONSENT); ?>" value="0" />
                        <label for="ts-search-statistics-require-consent" class="ts-toggle">
                            <input
                                type="checkbox"
                                id="ts-search-statistics-require-consent"
                                name="<?php echo esc_attr(Settings::OPTION_SEARCH_LOGGING_REQUIRE_CONSENT); ?>"
                                value="1"
                                <?php checked(1, (int) get_option(Settings::OPTION_SEARCH_LOGGING_REQUIRE_CONSENT, 0)); ?>
                                class="ts-toggle__input"
                            />
                            <span class="ts-toggle__track" aria-hidden="true"><span class="ts-toggle__thumb"></span></span>
                            <span class="ts-toggle__status"><span class="ts-toggle__status-on"><?php esc_html_e('On', 'typesense-search'); ?></span><span class="ts-toggle__status-off"><?php esc_html_e('Off', 'typesense-search'); ?></span></span>
                        </label>
                        <p class="ts-field__description"><?php esc_html_e('When enabled, no search term or session storage is created until your consent platform explicitly grants statistics consent.', 'typesense-search'); ?></p>
                    </div>
                </div>

                <div id="ts-search-statistics-consent-integration" class="ts-search-statistics-info"<?php echo (int) get_option(Settings::OPTION_SEARCH_LOGGING_REQUIRE_CONSENT, 0) ? '' : ' hidden'; ?>>
                    <h3><?php esc_html_e('Connect your consent platform', 'typesense-search'); ?></h3>
                    <p><?php esc_html_e('Run this on every page containing a search field when Pressidium Cookie Consent, Cookiebot, or another consent platform grants or withdraws statistics consent. Run it before search logging is allowed to start.', 'typesense-search'); ?></p>
                    <pre><code>function setTypesenseSearchStatisticsConsent(granted) {
  window.typesenseSearchStatisticsConsent = granted === true;
  window.dispatchEvent(new CustomEvent('typesense-search:statistics-consent', {
    detail: { granted: granted === true }
  }));
}

// Call this from your consent platform callback:
setTypesenseSearchStatisticsConsent(true);</code></pre>
                    <p><?php esc_html_e('Replace the final example with the boolean consent value from your platform. The plugin treats consent as denied until it receives true. When false is sent, pending logging stops and the plugin removes its session storage.', 'typesense-search'); ?></p>
                </div>

                <div class="ts-field">
                    <label for="ts-search-statistics-delay" class="ts-field__label"><?php esc_html_e('Search registration delay (seconds)', 'typesense-search'); ?></label>
                    <div class="ts-field__body">
                        <input type="number" id="ts-search-statistics-delay" name="<?php echo esc_attr(Settings::OPTION_SEARCH_LOGGING_DELAY_SECONDS); ?>" value="<?php echo esc_attr(get_option(Settings::OPTION_SEARCH_LOGGING_DELAY_SECONDS, 1)); ?>" class="small-text ts-field__input" min="0" max="30" step="1" />
                        <p class="ts-field__description"><?php esc_html_e('How long a completed query must remain unchanged before it is recorded. This is independent of the search-result debounce setting.', 'typesense-search'); ?></p>
                    </div>
                </div>

                <div class="ts-field">
                    <label for="ts-search-statistics-minimum-characters" class="ts-field__label"><?php esc_html_e('Minimum search-term characters', 'typesense-search'); ?></label>
                    <div class="ts-field__body">
                        <input type="number" id="ts-search-statistics-minimum-characters" name="<?php echo esc_attr(Settings::OPTION_SEARCH_LOGGING_MINIMUM_CHARACTERS); ?>" value="<?php echo esc_attr(get_option(Settings::OPTION_SEARCH_LOGGING_MINIMUM_CHARACTERS, 3)); ?>" class="small-text ts-field__input" min="1" max="50" step="1" />
                        <p class="ts-field__description"><?php esc_html_e('Shorter queries are searched normally but are not included in statistics.', 'typesense-search'); ?></p>
                    </div>
                </div>

                <div class="ts-field">
                    <label for="ts-search-statistics-retention-days" class="ts-field__label"><?php esc_html_e('Statistics retention (days)', 'typesense-search'); ?></label>
                    <div class="ts-field__body">
                        <input type="number" id="ts-search-statistics-retention-days" name="<?php echo esc_attr(Settings::OPTION_SEARCH_STATISTICS_RETENTION_DAYS); ?>" value="<?php echo esc_attr(get_option(Settings::OPTION_SEARCH_STATISTICS_RETENTION_DAYS, 90)); ?>" class="small-text ts-field__input" min="1" max="3650" step="1" />
                        <p class="ts-field__description"><?php esc_html_e('Search terms and anonymous session hashes older than this are permanently deleted.', 'typesense-search'); ?></p>
                    </div>
                </div>

                <div class="ts-search-statistics-info ts-search-statistics-info--retention">
                    <h3><?php esc_html_e('Automatic cleanup', 'typesense-search'); ?></h3>
                    <p><?php esc_html_e('A daily WordPress cron event removes expired statistics. Ensure WP-Cron runs reliably, or call the command below from your server cron.', 'typesense-search'); ?></p>
                    <pre><code>wp typesense prune-search-statistics</code></pre>
                    <p><?php esc_html_e('Alternatively, run the scheduled event with “wp cron event run typesense_search_prune_statistics”. The command uses the retention period set above.', 'typesense-search'); ?></p>
                </div>
            </div>
        </div>

        <?php submit_button(__('Save settings', 'typesense-search'), 'primary ts-settings__submit'); ?>
    </form>
</div>

<?php
// End advanced settings tab
?>
