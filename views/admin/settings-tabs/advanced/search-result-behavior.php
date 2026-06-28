<?php
use TypesenseSearch\Admin\Settings;
?>
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

        <?php if ($supportsPinnedResults) : ?>
        <div class="ts-field">
            <div class="ts-field__label"><?php esc_html_e('Enable pinned results', 'typesense-search'); ?></div>
            <div class="ts-field__body">
                <input type="hidden" name="<?php echo esc_attr(Settings::OPTION_PINNED_RESULTS_ENABLED); ?>" value="0" />
                <label for="ts-pinned-results-enabled" class="ts-toggle">
                    <input
                        type="checkbox"
                        id="ts-pinned-results-enabled"
                        name="<?php echo esc_attr(Settings::OPTION_PINNED_RESULTS_ENABLED); ?>"
                        value="1"
                        <?php checked(1, (int) get_option(Settings::OPTION_PINNED_RESULTS_ENABLED, 0)); ?>
                        class="ts-toggle__input"
                    />
                    <span class="ts-toggle__track" aria-hidden="true"><span class="ts-toggle__thumb"></span></span>
                    <span class="ts-toggle__status">
                        <span class="ts-toggle__status-on"><?php esc_html_e('On', 'typesense-search'); ?></span>
                        <span class="ts-toggle__status-off"><?php esc_html_e('Off', 'typesense-search'); ?></span>
                    </span>
                </label>
                <p class="ts-field__description">
                    <?php esc_html_e('Enable curated search rules that can pin selected results for specific search phrases. After saving, manage them from Settings > Pinned results.', 'typesense-search'); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
