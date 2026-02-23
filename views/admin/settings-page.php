<?php

/**
 * Settings page view for Typesense Search.
 *
 * Available variables:
 *  @var string        $activeTab        Current active tab slug.
 *  @var array<string,string> $tabs      Map of tab slug => label.
 *  @var \WP_Post_Type[] $postTypes      All indexable post types.
 *  @var string[]      $enabledPostTypes Slugs of currently enabled post types.
 *  @var array[]       $facets           Saved facet entries (field, label, placeholder).
 */

use TypesenseSearch\Admin\Settings;

$pageUrl = admin_url('options-general.php?page=' . Settings::PAGE_SLUG);
?>

<div class="wrap ts-settings">

    <h1 class="ts-settings__title">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="ts-settings__icon"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <?php esc_html_e('Typesense Search', 'typesense-search'); ?>
    </h1>

    <p class="ts-settings__subtitle">
        <?php esc_html_e('Configure your Typesense server connection and choose which content to index.', 'typesense-search'); ?>
    </p>

    <?php settings_errors('typesense_search_notices'); ?>

    <!-- Tab navigation -->
    <nav class="nav-tab-wrapper ts-settings__tabs" aria-label="<?php esc_attr_e('Settings sections', 'typesense-search'); ?>">
        <?php foreach ($tabs as $slug => $label) : ?>
            <a href="<?php echo esc_url($pageUrl . '&tab=' . $slug); ?>"
               class="nav-tab <?php echo $activeTab === $slug ? 'nav-tab-active' : ''; ?>">
                <?php if ($slug === 'connection') : ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1"/></svg>
                <?php elseif ($slug === 'statistics') : ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                <?php else : ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                <?php endif; ?>
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- ── CONNECTION TAB ────────────────────────────────────────── -->
    <?php if ($activeTab === 'connection') : ?>

    <div class="ts-settings__panel" id="ts-tab-connection">
        <form method="post" action="options.php">
            <?php settings_fields(Settings::OPTION_GROUP_CONNECTION); ?>

            <div class="ts-settings__card">
                <div class="ts-settings__card-header">
                    <h2><?php esc_html_e('Server', 'typesense-search'); ?></h2>
                    <p><?php esc_html_e('Point the plugin to your Typesense instance. You can self-host Typesense or use Typesense Cloud at cloud.typesense.org.', 'typesense-search'); ?></p>
                </div>

                <div class="ts-settings__fields">

                    <!-- Remote (host) -->
                    <div class="ts-field">
                        <label for="ts-remote" class="ts-field__label">
                            <?php esc_html_e('Host', 'typesense-search'); ?>
                            <span class="ts-field__required" aria-hidden="true">*</span>
                        </label>
                        <div class="ts-field__body">
                            <input
                                type="url"
                                id="ts-remote"
                                name="<?php echo esc_attr(Settings::OPTION_REMOTE); ?>"
                                value="<?php echo esc_attr(get_option(Settings::OPTION_REMOTE)); ?>"
                                placeholder="https://your-instance.typesense.net"
                                class="regular-text ts-field__input"
                                spellcheck="false"
                                autocomplete="off"
                            />
                            <p class="ts-field__description">
                                <?php esc_html_e('The full URL to your Typesense server, including protocol and port if needed (e.g. https://search.example.com:8108).', 'typesense-search'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Index / Collection name -->
                    <div class="ts-field">
                        <label for="ts-index-name" class="ts-field__label">
                            <?php esc_html_e('Collection name', 'typesense-search'); ?>
                            <span class="ts-field__required" aria-hidden="true">*</span>
                        </label>
                        <div class="ts-field__body">
                            <input
                                type="text"
                                id="ts-index-name"
                                name="<?php echo esc_attr(Settings::OPTION_INDEX_NAME); ?>"
                                value="<?php echo esc_attr(get_option(Settings::OPTION_INDEX_NAME)); ?>"
                                placeholder="my-wordpress-site"
                                class="regular-text ts-field__input"
                                spellcheck="false"
                                autocomplete="off"
                            />
                            <p class="ts-field__description">
                                <?php esc_html_e('The name of the Typesense collection where content will be indexed. Use lowercase letters, numbers, and hyphens only.', 'typesense-search'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Test connection -->
                    <div class="ts-field ts-field--action">
                        <span class="ts-field__label"><?php esc_html_e('Connection', 'typesense-search'); ?></span>
                        <div class="ts-field__body">
                            <div class="ts-connection-test">
                                <button type="button" id="ts-test-connection" class="button button-secondary ts-connection-test__button">
                                    <svg class="ts-connection-test__spinner" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                                    <svg class="ts-connection-test__icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1"/></svg>
                                    <?php esc_html_e('Test connection and check collection', 'typesense-search'); ?>
                                </button>
                            </div>
                            <p class="ts-field__description">
                                <?php esc_html_e('Verify that the host is reachable, the Admin API key is accepted and the collection exists.', 'typesense-search'); ?>
                            </p>
                            <div id="ts-connection-result" class="ts-connection-result" hidden>
                                <span class="ts-connection-result__dot" aria-hidden="true"></span>
                                <span class="ts-connection-result__message"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Create collection (shown only when collection does not exist) -->
                    <div class="ts-field ts-field--action" id="ts-create-collection-field" hidden>
                        <span class="ts-field__label"><?php esc_html_e('Create collection', 'typesense-search'); ?></span>
                        <div class="ts-field__body">
                            <div id="ts-create-collection-wrap" class="ts-connection-test">
                                <button type="button" id="ts-create-collection" class="button button-secondary ts-connection-test__button">
                                    <svg class="ts-connection-test__spinner" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                                    <svg class="ts-connection-test__icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    <?php esc_html_e('Create collection', 'typesense-search'); ?>
                                </button>

                                <div id="ts-create-collection-result" class="ts-connection-result" hidden>
                                    <span class="ts-connection-result__dot" aria-hidden="true"></span>
                                    <span class="ts-connection-result__message"></span>
                                </div>
                            </div>
                            <p class="ts-field__description">
                                <?php esc_html_e('The collection does not yet exist in Typesense.', 'typesense-search'); ?> <br />
                                <?php esc_html_e('Click the button to create it before indexing.', 'typesense-search'); ?>
                            </p>
                        </div>
                    </div>

                </div>
            </div>

            <div class="ts-settings__card">
                <div class="ts-settings__card-header">
                    <h2><?php esc_html_e('API Keys', 'typesense-search'); ?></h2>
                    <p><?php esc_html_e('Keep your admin key secret — it grants full access to your cluster. The search key is safe to expose in your front-end JavaScript.', 'typesense-search'); ?></p>
                </div>

                <div class="ts-settings__fields">

                    <!-- Admin key -->
                    <div class="ts-field">
                        <label for="ts-admin-key" class="ts-field__label">
                            <?php esc_html_e('Admin API key', 'typesense-search'); ?>
                            <span class="ts-field__required" aria-hidden="true">*</span>
                        </label>
                        <div class="ts-field__body">
                            <div class="ts-field__input-wrap">
                                <input
                                    type="password"
                                    id="ts-admin-key"
                                    name="<?php echo esc_attr(Settings::OPTION_ADMIN_KEY); ?>"
                                    value="<?php echo esc_attr(get_option(Settings::OPTION_ADMIN_KEY)); ?>"
                                    placeholder="••••••••••••••••••••••••"
                                    class="regular-text ts-field__input"
                                    spellcheck="false"
                                    autocomplete="new-password"
                                />
                                <button type="button" class="button ts-field__toggle-visibility" aria-label="<?php esc_attr_e('Toggle key visibility', 'typesense-search'); ?>" data-target="ts-admin-key">
                                    <svg class="ts-icon-eye" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="ts-icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                            </div>
                            <p class="ts-field__description">
                                <?php esc_html_e('Used server-side for indexing operations. Never expose this key publicly.', 'typesense-search'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Search key (public) -->
                    <div class="ts-field">
                        <label for="ts-search-key" class="ts-field__label">
                            <?php esc_html_e('Search API key', 'typesense-search'); ?>
                            <span class="ts-field__required" aria-hidden="true">*</span>
                        </label>
                        <div class="ts-field__body">
                            <div class="ts-field__input-wrap">
                                <input
                                    type="password"
                                    id="ts-search-key"
                                    name="<?php echo esc_attr(Settings::OPTION_SEARCH_KEY); ?>"
                                    value="<?php echo esc_attr(get_option(Settings::OPTION_SEARCH_KEY)); ?>"
                                    placeholder="••••••••••••••••••••••••"
                                    class="regular-text ts-field__input"
                                    spellcheck="false"
                                    autocomplete="new-password"
                                />
                                <button type="button" class="button ts-field__toggle-visibility" aria-label="<?php esc_attr_e('Toggle key visibility', 'typesense-search'); ?>" data-target="ts-search-key">
                                    <svg class="ts-icon-eye" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="ts-icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                            </div>
                            <p class="ts-field__description">
                                <?php esc_html_e('A search-only API key used in your front-end JavaScript. This key has read-only access and is safe to include in public code.', 'typesense-search'); ?>
                            </p>

                            <div id="ts-gen-key-wrap" <?php echo get_option(Settings::OPTION_SEARCH_KEY) ? 'hidden' : ''; ?>>
                                <button type="button" id="ts-generate-search-key" class="button button-secondary ts-connection-test__button">
                                    <svg class="ts-connection-test__spinner" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                                    <svg class="ts-connection-test__icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                                    <?php esc_html_e('Generate search key', 'typesense-search'); ?>
                                </button>

                                <div id="ts-gen-key-result" class="ts-connection-result" hidden>
                                    <span class="ts-connection-result__dot" aria-hidden="true"></span>
                                    <span class="ts-connection-result__message"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <?php submit_button(__('Save settings', 'typesense-search'), 'primary ts-settings__submit'); ?>
        </form>
    </div>

    <?php endif; ?>

    <!-- ── CONTENT TAB ───────────────────────────────────────────── -->
    <?php if ($activeTab === 'content') : ?>

    <div class="ts-settings__panel" id="ts-tab-content">
        <form method="post" action="options.php">
            <?php settings_fields(Settings::OPTION_GROUP_CONTENT); ?>

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

    <?php endif; ?>

    <!-- ── FACETTING TAB ────────────────────────────────────────────── -->
    <?php if ($activeTab === 'facetting') : ?>

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
                <div id="ts-facet-list" class="ts-facet-list">
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
                <div class="ts-facet-actions">
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

    <?php endif; ?>

    <!-- ── STATISTICS TAB ───────────────────────────────────────────── -->
    <?php if ($activeTab === 'statistics') : ?>

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

    <?php endif; ?>

</div><!-- .wrap -->
