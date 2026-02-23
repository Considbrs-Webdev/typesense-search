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
