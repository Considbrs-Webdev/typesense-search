<?php
use TypesenseSearch\Admin\Settings;

if ($activeTab !== 'content') {
    return;
}
?>

<div class="ts-settings__panel" id="ts-tab-content">
    <form method="post" action="options.php">
        <?php settings_fields(Settings::OPTION_GROUP_CONTENT); ?>

        <div class="ts-settings__card">
            <div class="ts-settings__card-header">
                <h2><?php esc_html_e('General settings', 'typesense-search'); ?></h2>
                <p><?php esc_html_e('Basic configuration for Typesense search behavior.', 'typesense-search'); ?></p>
            </div>

            <div class="ts-settings__fields">
                <div class="ts-field">
                    <label for="ts-hits-per-page" class="ts-field__label">
                        <?php esc_html_e('Hits per page', 'typesense-search'); ?>
                    </label>
                    <div class="ts-field__body">
                        <input
                            type="number"
                            id="ts-hits-per-page"
                            name="<?php echo esc_attr(Settings::OPTION_HITS_PER_PAGE); ?>"
                            value="<?php echo esc_attr(get_option(Settings::OPTION_HITS_PER_PAGE, 10)); ?>"
                            class="small-text ts-field__input"
                            step="1"
                            min="1"
                        />
                        <p class="ts-field__description">
                            <?php esc_html_e('Number of search results to display per page.', 'typesense-search'); ?>
                        </p>
                    </div>
                </div>
                <?php if (Settings::isModularityAvailable()) : ?>
                <div class="ts-field">
                    <div class="ts-field__label">
                        <?php esc_html_e('Index Modularity content', 'typesense-search'); ?>
                    </div>
                    <div class="ts-field__body">
                        <input type="hidden" name="<?php echo esc_attr(Settings::OPTION_INDEX_MODULARITY); ?>" value="0" />

                        <label for="ts-index-modularity" class="ts-toggle">
                            <input
                                type="checkbox"
                                id="ts-index-modularity"
                                name="<?php echo esc_attr(Settings::OPTION_INDEX_MODULARITY); ?>"
                                value="1"
                                <?php checked(1, (int) get_option(Settings::OPTION_INDEX_MODULARITY, 0)); ?>
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
                            <?php esc_html_e('When enabled, content that is added via Modularity modules will be included in the Typesense index. If disabled, content placed in module slots like sidebars will not be indexed for search.', 'typesense-search'); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                <div class="ts-field">
                    <div class="ts-field__label">
                        <?php esc_html_e('Debounce search', 'typesense-search'); ?>
                    </div>
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
                            <span class="ts-toggle__track" aria-hidden="true">
                                <span class="ts-toggle__thumb"></span>
                            </span>
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
                <script>
                (function () {
                    var toggle = document.getElementById('ts-debounce');
                    var delayField = document.getElementById('ts-debounce-delay-field');
                    if (toggle && delayField) {
                        toggle.addEventListener('change', function () {
                            delayField.hidden = !this.checked;
                        });
                    }
                }());
                </script>
            </div>
        </div>

        <div class="ts-settings__card">
            <div class="ts-settings__card-header">
                <h2><?php esc_html_e('Post types', 'typesense-search'); ?></h2>
                <p><?php esc_html_e('Choose which content types should be synced to Typesense. Disabled types are excluded from indexing and search results.', 'typesense-search'); ?></p>
            </div>

            <?php if (empty($postTypes)) : ?>
                <p class="ts-settings__empty">
                    <?php esc_html_e('No public post types found.', 'typesense-search'); ?>
                </p>
            <?php else : ?>
            <ul class="ts-toggle-list">
                <?php foreach ($postTypes as $postType) : ?>
                <li class="ts-toggle-list__item">
                    <label class="ts-toggle-item" for="ts-pt-<?php echo esc_attr($postType->name); ?>">

                        <span class="ts-toggle-item__meta">
                            <span class="ts-toggle-item__label"><?php echo esc_html($postType->label); ?></span>
                            <span class="ts-toggle-item__slug"><?php echo esc_html($postType->name); ?></span>
                        </span>

                        <span class="ts-toggle" role="presentation">
                            <input
                                type="checkbox"
                                id="ts-pt-<?php echo esc_attr($postType->name); ?>"
                                name="<?php echo esc_attr(Settings::OPTION_POST_TYPES); ?>[]"
                                value="<?php echo esc_attr($postType->name); ?>"
                                <?php checked(in_array($postType->name, $enabledPostTypes, true)); ?>
                                class="ts-toggle__input"
                            />
                            <span class="ts-toggle__track" aria-hidden="true">
                                <span class="ts-toggle__thumb"></span>
                            </span>
                            <span class="ts-toggle__status">
                                <span class="ts-toggle__status-on"><?php esc_html_e('On', 'typesense-search'); ?></span>
                                <span class="ts-toggle__status-off"><?php esc_html_e('Off', 'typesense-search'); ?></span>
                            </span>
                        </span>

                    </label>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <?php submit_button(__('Save settings', 'typesense-search'), 'primary ts-settings__submit'); ?>
    </form>
</div>

<?php
// End content tab
?>
