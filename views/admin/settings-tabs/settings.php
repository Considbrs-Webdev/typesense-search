<?php
use TypesenseSearch\Admin\Settings;

if ($activeTab !== 'content') {
    return;
}
?>

<div class="ts-settings__panel" id="ts-tab-settings">
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

                <div class="ts-field">
                    <label for="ts-sort-display" class="ts-field__label">
                        <?php esc_html_e('Sort control style', 'typesense-search'); ?>
                    </label>
                    <div class="ts-field__body">
                        <select
                            id="ts-sort-display"
                            name="<?php echo esc_attr(Settings::OPTION_SORT_DISPLAY); ?>"
                            class="ts-field__input"
                        >
                            <option value="radio" <?php selected('radio', get_option(Settings::OPTION_SORT_DISPLAY, 'radio')); ?>>
                                <?php esc_html_e('Radio buttons', 'typesense-search'); ?>
                            </option>
                            <option value="dropdown" <?php selected('dropdown', get_option(Settings::OPTION_SORT_DISPLAY, 'radio')); ?>>
                                <?php esc_html_e('Dropdown', 'typesense-search'); ?>
                            </option>
                        </select>
                        <p class="ts-field__description">
                            <?php esc_html_e('How the sort options are displayed on the search page. "Radio buttons" shows inline options; "Dropdown" uses a select element like the facet filters.', 'typesense-search'); ?>
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

            </div>
        </div>

        <div class="ts-settings__card">
            <div class="ts-settings__card-header">
                <h2><?php esc_html_e('Post types', 'typesense-search'); ?></h2>
                <p><?php esc_html_e('Choose which content types should be synced to Typesense. Disabled types are excluded from indexing and search results.', 'typesense-search'); ?></p>
                <?php if (!$pdfToTextAvailable) : ?>
                <p class="ts-settings__notice ts-settings__notice--warning">
                    <?php esc_html_e('PDF indexing requires the pdftotext binary to be installed on the server.', 'typesense-search'); ?>
                </p>
                <?php endif; ?>
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
                <?php if ($postType->name === 'page') : ?>
                <li class="ts-toggle-list__item<?php echo !$pdfToTextAvailable ? ' ts-toggle-list__item--disabled' : ''; ?>">
                    <input type="hidden" name="<?php echo esc_attr(Settings::OPTION_INDEX_PDF); ?>" value="0" />
                    <label class="ts-toggle-item" for="ts-index-pdf">

                        <span class="ts-toggle-item__meta">
                            <span class="ts-toggle-item__label"><?php esc_html_e('PDF files', 'typesense-search'); ?></span>
                            <span class="ts-toggle-item__slug">attachment</span>
                        </span>

                        <span class="ts-toggle" role="presentation">
                            <input
                                type="checkbox"
                                id="ts-index-pdf"
                                name="<?php echo esc_attr(Settings::OPTION_INDEX_PDF); ?>"
                                value="1"
                                <?php checked(1, !$pdfToTextAvailable ? 0 : (int) get_option(Settings::OPTION_INDEX_PDF, 0)); ?>
                                class="ts-toggle__input"
                                <?php echo !$pdfToTextAvailable ? 'disabled' : ''; ?>
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
                <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <?php submit_button(__('Save settings', 'typesense-search'), 'primary ts-settings__submit'); ?>
    </form>
</div>

<?php
// End settings tab
?>
