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
                    <?php esc_html_e('Enable curated search rules that can pin selected results for specific search phrases. After saving, manage them from Typesense search > Pinned results.', 'typesense-search'); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($supportsSynonyms) : ?>
        <div class="ts-field">
            <div class="ts-field__label"><?php esc_html_e('Enable synonyms', 'typesense-search'); ?></div>
            <div class="ts-field__body">
                <input type="hidden" name="<?php echo esc_attr(Settings::OPTION_SYNONYMS_ENABLED); ?>" value="0" />
                <label for="ts-synonyms-enabled" class="ts-toggle">
                    <input
                        type="checkbox"
                        id="ts-synonyms-enabled"
                        name="<?php echo esc_attr(Settings::OPTION_SYNONYMS_ENABLED); ?>"
                        value="1"
                        <?php checked(1, (int) get_option(Settings::OPTION_SYNONYMS_ENABLED, 0)); ?>
                        class="ts-toggle__input"
                    />
                    <span class="ts-toggle__track" aria-hidden="true"><span class="ts-toggle__thumb"></span></span>
                    <span class="ts-toggle__status">
                        <span class="ts-toggle__status-on"><?php esc_html_e('On', 'typesense-search'); ?></span>
                        <span class="ts-toggle__status-off"><?php esc_html_e('Off', 'typesense-search'); ?></span>
                    </span>
                </label>
                <p class="ts-field__description">
                    <?php esc_html_e('Enable synonym rules so a search for one word also matches related words you define. After saving, manage them from Typesense search > Synonyms.', 'typesense-search'); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <div class="ts-field">
            <div class="ts-field__label"><?php esc_html_e('Enable external pages', 'typesense-search'); ?></div>
            <div class="ts-field__body">
                <input type="hidden" name="<?php echo esc_attr(Settings::OPTION_EXTERNAL_PAGES_ENABLED); ?>" value="0" />
                <label for="ts-external-pages-enabled" class="ts-toggle">
                    <input
                        type="checkbox"
                        id="ts-external-pages-enabled"
                        name="<?php echo esc_attr(Settings::OPTION_EXTERNAL_PAGES_ENABLED); ?>"
                        value="1"
                        <?php checked(1, (int) get_option(Settings::OPTION_EXTERNAL_PAGES_ENABLED, 0)); ?>
                        class="ts-toggle__input"
                    />
                    <span class="ts-toggle__track" aria-hidden="true"><span class="ts-toggle__thumb"></span></span>
                    <span class="ts-toggle__status">
                        <span class="ts-toggle__status-on"><?php esc_html_e('On', 'typesense-search'); ?></span>
                        <span class="ts-toggle__status-off"><?php esc_html_e('Off', 'typesense-search'); ?></span>
                    </span>
                </label>
                <p class="ts-field__description">
                    <?php esc_html_e('Let editors add searchable entries for pages that do not exist in WordPress, such as booking systems or pages on other domains. After saving, manage them from Typesense search > External pages.', 'typesense-search'); ?>
                </p>
            </div>
        </div>

        <?php if ($supportsStemming) : ?>
        <div class="ts-field">
            <div class="ts-field__label"><?php esc_html_e('Enable stemming', 'typesense-search'); ?></div>
            <div class="ts-field__body">
                <input type="hidden" name="<?php echo esc_attr(Settings::OPTION_STEMMING_ENABLED); ?>" value="0" />
                <label for="ts-stemming-enabled" class="ts-toggle">
                    <input
                        type="checkbox"
                        id="ts-stemming-enabled"
                        name="<?php echo esc_attr(Settings::OPTION_STEMMING_ENABLED); ?>"
                        value="1"
                        <?php checked(1, (int) get_option(Settings::OPTION_STEMMING_ENABLED, 0)); ?>
                        class="ts-toggle__input"
                    />
                    <span class="ts-toggle__track" aria-hidden="true"><span class="ts-toggle__thumb"></span></span>
                    <span class="ts-toggle__status">
                        <span class="ts-toggle__status-on"><?php esc_html_e('On', 'typesense-search'); ?></span>
                        <span class="ts-toggle__status-off"><?php esc_html_e('Off', 'typesense-search'); ?></span>
                    </span>
                </label>
                <p class="ts-field__description">
                    <?php
                    printf(
                        /* translators: %s: two-letter language code detected from the site's locale, e.g. "sv" */
                        esc_html__('Matches word variations (e.g. "running" also matches "run") on the title, content, excerpt, and extra search terms fields, using your site\'s detected language ("%s"). Changing this setting requires rebuilding your Typesense collection — run "wp typesense rebuild" after saving.', 'typesense-search'),
                        esc_html($stemmingLocale)
                    );
                    ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($supportsAutoEmbedding)) :
            $vectorEnabled       = (int) get_option(Settings::OPTION_VECTOR_ENABLED, 0);
            $vectorDefaultMode   = \TypesenseSearch\Typesense\EmbeddingModels::sanitizeSearchMode(
                get_option(Settings::OPTION_VECTOR_DEFAULT_MODE, \TypesenseSearch\Typesense\EmbeddingModels::DEFAULT_SEARCH_MODE)
            );
            $vectorModeSwitcher  = (int) get_option(Settings::OPTION_VECTOR_MODE_SWITCHER, 0);
            $presets             = $vectorModelPresets ?? \TypesenseSearch\Typesense\EmbeddingModels::presets();
            $currentModel        = $effectiveVectorModel ?? \TypesenseSearch\Typesense\EmbeddingModels::DEFAULT_MODEL;
            $isNetworkMode       = !empty($networkMode);
            $modelConstantLocked = \TypesenseSearch\ConstantsLoader::isDefinedAsConstant(Settings::OPTION_VECTOR_MODEL);
            $isPresetModel       = array_key_exists($currentModel, $presets);
        ?>
        <div class="ts-field">
            <div class="ts-field__label"><?php esc_html_e('Enable embedded search', 'typesense-search'); ?></div>
            <div class="ts-field__body">
                <input type="hidden" name="<?php echo esc_attr(Settings::OPTION_VECTOR_ENABLED); ?>" value="0" />
                <label for="ts-vector-enabled" class="ts-toggle">
                    <input
                        type="checkbox"
                        id="ts-vector-enabled"
                        name="<?php echo esc_attr(Settings::OPTION_VECTOR_ENABLED); ?>"
                        value="1"
                        <?php checked(1, $vectorEnabled); ?>
                        class="ts-toggle__input"
                    />
                    <span class="ts-toggle__track" aria-hidden="true"><span class="ts-toggle__thumb"></span></span>
                    <span class="ts-toggle__status">
                        <span class="ts-toggle__status-on"><?php esc_html_e('On', 'typesense-search'); ?></span>
                        <span class="ts-toggle__status-off"><?php esc_html_e('Off', 'typesense-search'); ?></span>
                    </span>
                </label>
                <p class="ts-field__description">
                    <?php esc_html_e('Adds AI vector embeddings to your Typesense collection for semantic and hybrid search. Enabling this or changing the model requires running "wp typesense rebuild"; live search safely stays in keyword mode until the collection is rebuilt.', 'typesense-search'); ?>
                </p>
            </div>
        </div>

        <div id="ts-vector-settings-fields"<?php echo $vectorEnabled ? '' : ' hidden'; ?>>
            <div class="ts-field">
                <label for="ts-vector-model-mode" class="ts-field__label">
                    <?php esc_html_e('Embedding model', 'typesense-search'); ?>
                </label>
                <div class="ts-field__body">
                    <?php if ($isNetworkMode) : ?>
                        <code><?php echo esc_html($currentModel); ?></code>
                        <p class="ts-field__description">
                            <?php esc_html_e('Managed by the network administrator so all sites on the shared Typesense server use a compatible embedding model.', 'typesense-search'); ?>
                            <?php if (current_user_can('manage_network_options')) : ?>
                                <a href="<?php echo esc_url(network_admin_url('settings.php?page=typesense-network&tab=connection')); ?>">
                                    <?php esc_html_e('Network settings', 'typesense-search'); ?>
                                </a>
                            <?php endif; ?>
                        </p>
                    <?php elseif ($modelConstantLocked) : ?>
                        <input
                            type="text"
                            id="ts-vector-model-mode"
                            class="regular-text ts-field__input ts-field__input--env-locked"
                            value="<?php echo esc_attr($currentModel); ?>"
                            disabled
                        />
                        <p class="ts-field__env-notice"><span aria-hidden="true">🔒</span> <?php esc_html_e('Set via TYPESENSE_VECTOR_MODEL constant — read only.', 'typesense-search'); ?></p>
                    <?php else : ?>
                        <select id="ts-vector-model-mode" class="ts-field__input">
                            <?php foreach ($presets as $slug => $label) : ?>
                                <option value="<?php echo esc_attr($slug); ?>" <?php selected($isPresetModel ? $currentModel : '', $slug); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="<?php echo esc_attr(\TypesenseSearch\Typesense\EmbeddingModels::CUSTOM_CHOICE); ?>" <?php selected(false, $isPresetModel); ?>>
                                <?php esc_html_e('Custom model…', 'typesense-search'); ?>
                            </option>
                        </select>
                        <input
                            type="hidden"
                            id="ts-vector-model"
                            name="<?php echo esc_attr(Settings::OPTION_VECTOR_MODEL); ?>"
                            value="<?php echo esc_attr($currentModel); ?>"
                        />
                        <input
                            type="text"
                            id="ts-vector-model-custom"
                            class="regular-text ts-field__input"
                            placeholder="ts/paraphrase-multilingual-mpnet-base-v2"
                            value="<?php echo esc_attr($isPresetModel ? '' : $currentModel); ?>"
                            <?php echo $isPresetModel ? 'hidden' : ''; ?>
                        />
                        <p class="ts-field__description">
                            <?php esc_html_e('Choose a built-in Typesense local model (downloaded automatically on the Typesense server), or select "Custom model…" to specify another valid "ts/…" model.', 'typesense-search'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ts-field">
                <label for="ts-vector-default-mode" class="ts-field__label">
                    <?php esc_html_e('Default search mode', 'typesense-search'); ?>
                </label>
                <div class="ts-field__body">
                    <select
                        id="ts-vector-default-mode"
                        name="<?php echo esc_attr(Settings::OPTION_VECTOR_DEFAULT_MODE); ?>"
                        class="ts-field__input"
                    >
                        <option value="hybrid" <?php selected('hybrid', $vectorDefaultMode); ?>>
                            <?php esc_html_e('Hybrid (Keyword + AI Vector)', 'typesense-search'); ?>
                        </option>
                        <option value="keyword" <?php selected('keyword', $vectorDefaultMode); ?>>
                            <?php esc_html_e('Keyword (Lexical)', 'typesense-search'); ?>
                        </option>
                        <option value="semantic" <?php selected('semantic', $vectorDefaultMode); ?>>
                            <?php esc_html_e('Semantic (AI Vector only)', 'typesense-search'); ?>
                        </option>
                    </select>
                    <p class="ts-field__description">
                        <?php esc_html_e('Search mode used by default when embedded search is active on the collection.', 'typesense-search'); ?>
                    </p>
                </div>
            </div>

            <div class="ts-field">
                <div class="ts-field__label"><?php esc_html_e('Show mode switcher on search page', 'typesense-search'); ?></div>
                <div class="ts-field__body">
                    <input type="hidden" name="<?php echo esc_attr(Settings::OPTION_VECTOR_MODE_SWITCHER); ?>" value="0" />
                    <label for="ts-vector-mode-switcher" class="ts-toggle">
                        <input
                            type="checkbox"
                            id="ts-vector-mode-switcher"
                            name="<?php echo esc_attr(Settings::OPTION_VECTOR_MODE_SWITCHER); ?>"
                            value="1"
                            <?php checked(1, $vectorModeSwitcher); ?>
                            class="ts-toggle__input"
                        />
                        <span class="ts-toggle__track" aria-hidden="true"><span class="ts-toggle__thumb"></span></span>
                        <span class="ts-toggle__status">
                            <span class="ts-toggle__status-on"><?php esc_html_e('On', 'typesense-search'); ?></span>
                            <span class="ts-toggle__status-off"><?php esc_html_e('Off', 'typesense-search'); ?></span>
                        </span>
                    </label>
                    <p class="ts-field__description">
                        <?php esc_html_e('Allow visitors to switch between Hybrid, Keyword, and Semantic modes in the search filter panel.', 'typesense-search'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($networkMode) && is_array($multiSearchResolution ?? null)) : ?>
        <div class="ts-field">
            <div class="ts-field__label"><?php esc_html_e('Cross-site multi-search', 'typesense-search'); ?></div>
            <div class="ts-field__body">
                <?php if (!empty($multiSearchResolution['enabled'])) :
                    $siblingNames = array_map(
                        static fn (array $item): string => (string) $item['site_name'],
                        array_slice($multiSearchResolution['collections'], 1)
                    );
                ?>
                    <p style="margin-top: 0;">
                        <strong><?php esc_html_e('Active', 'typesense-search'); ?></strong> &mdash;
                        <?php echo esc_html(sprintf(
                            /* translators: %s: comma-separated list of sibling site names */
                            __('Search on this site also includes results from: %s.', 'typesense-search'),
                            implode(', ', $siblingNames)
                        )); ?>
                    </p>
                <?php elseif (!empty($multiSearchResolution['configured'])) : ?>
                    <p style="margin-top: 0;">
                        <strong><?php esc_html_e('Configured (inactive)', 'typesense-search'); ?></strong> &mdash;
                        <?php esc_html_e('Cross-site targets are configured, but none are currently active/ready. This site currently searches only its own index.', 'typesense-search'); ?>
                    </p>
                <?php else : ?>
                    <p style="margin-top: 0;">
                        <?php esc_html_e('Off — this site only searches its own index.', 'typesense-search'); ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($multiSearchResolution['vector_paused_reason'])) : ?>
                    <div class="notice notice-warning inline" style="margin: 8px 0;">
                        <p><?php echo esc_html((string) $multiSearchResolution['vector_paused_reason']); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($multiSearchResolution['skipped_targets'])) : ?>
                    <div class="notice notice-warning inline" style="margin: 8px 0;">
                        <p>
                            <?php
                            $skippedItems = array_map(
                                static fn (array $s): string => sprintf('%s (%s)', $s['site_name'], $s['reason']),
                                (array) $multiSearchResolution['skipped_targets']
                            );
                            echo esc_html(sprintf(
                                /* translators: %s: list of skipped target sites */
                                __('Skipped target site(s): %s', 'typesense-search'),
                                implode('; ', $skippedItems)
                            ));
                            ?>
                        </p>
                    </div>
                <?php endif; ?>

                <p class="ts-field__description">
                    <?php esc_html_e('Cross-site multi-search is managed by the network administrator.', 'typesense-search'); ?>
                    <?php if (current_user_can('manage_network_options')) : ?>
                        <a href="<?php echo esc_url(network_admin_url('settings.php?page=typesense-network&tab=multi-search')); ?>">
                            <?php esc_html_e('Configure in Network settings', 'typesense-search'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
