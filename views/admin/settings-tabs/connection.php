<?php
use TypesenseSearch\Admin\Settings;

if ($activeTab !== 'connection') {
    return;
}
?>

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
                                <span class="ts-create-collection-result__message"></span>
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
                                placeholder=""
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
                                placeholder=""
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

<?php
// End connection tab
?>
