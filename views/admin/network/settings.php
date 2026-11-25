<?php
use TypesenseSearch\Admin\NetworkSettingsPage;
use TypesenseSearch\Typesense\ProvisioningCredentials;
$provisioningAvailable = ProvisioningCredentials::isAvailableFor($connection['remote']);
?>
<style>
.typesense-network-heading { display: flex; align-items: center; flex-wrap: wrap; gap: 12px 20px; margin-bottom: 4px; }
.typesense-network-heading h1 { margin: 0; }
.typesense-network-environment { padding: 4px 9px; border: 1px solid #c3c4c7; border-radius: 3px; color: #50575e; font-size: 12px; white-space: nowrap; }
.typesense-network-intro { margin: 0; padding: 16px 24px; border-bottom: 1px solid #f0f0f1; }
.typesense-network-intro p { margin: 0 0 6px; font-size: 13px; color: #646970; }
.typesense-network-intro p:last-child { margin-bottom: 0; }
.typesense-network-table-wrap { overflow-x: auto; }
.typesense-network-table { table-layout: fixed; width: 100%; min-width: 760px; border: none; }
.typesense-network-table .typesense-col-selected { width: 60px; }
.typesense-network-table .typesense-col-site { width: 25%; }
.typesense-network-table .typesense-col-status { width: 30%; }
.typesense-network-table th, .typesense-network-table td { box-sizing: border-box; padding: 12px; vertical-align: top; overflow-wrap: anywhere; }
.typesense-network-table td:first-child, .typesense-network-table th:first-child { text-align: center; }
.typesense-network-state { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
.typesense-network-status { flex: 0 0 10px; width: 10px; height: 10px; border-radius: 50%; }
.typesense-network-status--active { background: #008a20; }
.typesense-network-status--disabled { background: #646970; }
.typesense-network-status--pending { background: #996800; }
.typesense-network-status--error { background: #d63638; }
.typesense-network-table p { font-weight: 400; margin: 8px 0; }
.typesense-network-table code { white-space: normal; overflow-wrap: anywhere; }
.typesense-network-table details { margin-top: 12px; }
.typesense-network-table summary { cursor: pointer; font-weight: 500; }
.typesense-network-table details[open] { padding: 12px; background: #fff; border: 1px solid #dcdcde; border-radius: 3px; }
.typesense-network-site-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.typesense-network-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin: 16px 0 0; }
.typesense-network-actions form { display: contents; }
</style>
<div class="wrap typesense-network ts-settings">
<div class="typesense-network-heading"><h1><?php esc_html_e('Typesense Search — Network settings', 'typesense-search'); ?></h1><span class="typesense-network-environment"><?php echo esc_html(sprintf(/* translators: %s: WordPress environment type, e.g. production */ __('Environment: %s', 'typesense-search'), wp_get_environment_type())); ?></span></div>
<p class="ts-settings__subtitle"><?php esc_html_e('Connect the network to a shared Typesense server, then choose which sites get their own index.', 'typesense-search'); ?></p>
<nav class="nav-tab-wrapper ts-settings__tabs">
<?php foreach ([
    'connection'   => __('Connection', 'typesense-search'),
    'sites'        => __('Sites', 'typesense-search'),
    'multi-search' => __('Multi-search', 'typesense-search'),
] as $slug => $label) : ?>
<a class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(network_admin_url('settings.php?page=typesense-network&tab=' . $slug)); ?>"><?php echo esc_html($label); ?></a>
<?php endforeach; ?>
</nav>
<div class="ts-settings__panel">
<?php $notice = get_site_transient('typesense_network_notice_' . get_current_user_id());
if (is_array($notice)) : delete_site_transient('typesense_network_notice_' . get_current_user_id()); ?>
<div class="notice <?php echo !empty($notice['success']) ? 'notice-success' : 'notice-error'; ?> inline"><p><?php echo esc_html(__($notice['message'], 'typesense-search')); ?></p></div>
<?php endif; ?>
<?php if (!empty($run)) : $nextId = (int) $run['sites'][0]; ?>
<div class="notice notice-info inline"><p><?php echo esc_html(sprintf(/* translators: 1: number of the site being set up, 2: total number of sites */ __('Setting up site %1$d of %2$d. Keep this page open until setup completes.', 'typesense-search'), $run['total'] - count($run['sites']) + 1, $run['total'])); ?></p>
<p><?php echo esc_html(get_home_url($nextId)); ?></p>
<form id="typesense-setup-next" method="post" action="<?php echo esc_url(\TypesenseSearch\Multisite\SiteUrls::admin($nextId, 'admin-post.php')); ?>">
<input type="hidden" name="action" value="<?php echo esc_attr(NetworkSettingsPage::ACTION); ?>">
<input type="hidden" name="site_id" value="<?php echo $nextId; ?>"><input type="hidden" name="network_id" value="<?php echo (int) $networkId; ?>">
<input type="hidden" name="operation" value="setup"><input type="hidden" name="tab" value="<?php echo esc_attr($run['tab']); ?>">
<input type="hidden" name="setup_run" value="<?php echo esc_attr($run['id']); ?>">
<?php wp_nonce_field(NetworkSettingsPage::ACTION . '_' . $nextId); ?>
<button type="submit" class="button"><?php esc_html_e('Continue setup', 'typesense-search'); ?></button>
</form></div>
<script>document.getElementById('typesense-setup-next').submit();</script>
<?php endif; ?>
<?php if ($network->conflict()) : ?>
<div class="notice notice-error inline"><p><?php esc_html_e('Remove TYPESENSE_COLLECTION and TYPESENSE_SEARCH_KEY constants before using network mode. Each site needs its own collection and key.', 'typesense-search'); ?></p></div>
<?php endif; ?>
<?php if (isset($_GET['saved'])) : ?><div class="notice notice-success inline"><p><?php esc_html_e('Settings saved.', 'typesense-search'); ?></p></div><?php endif; ?>
<?php if ($tab === 'connection') : ?>
<form id="typesense-network-connection" method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=typesense_network_save')); ?>">
<?php wp_nonce_field('typesense_network_save'); ?><input type="hidden" name="section" value="connection">

<div class="ts-settings__card">
    <div class="ts-settings__card-header">
        <h2><?php esc_html_e('Server', 'typesense-search'); ?></h2>
        <p><?php esc_html_e('Every site on the network shares this Typesense server. Set an index prefix if the same server is also used outside this network.', 'typesense-search'); ?></p>
    </div>
    <div class="ts-settings__fields">
        <?php foreach (['remote' => [__('Typesense host', 'typesense-search'), 'TYPESENSE_HOST'], 'frontend_host' => [__('Frontend host (optional)', 'typesense-search'), 'TYPESENSE_FRONTEND_HOST']] as $field => [$label, $constant]) :
            $locked = $network->constant($constant) !== '';
        ?>
        <div class="ts-field">
            <label for="<?php echo esc_attr($field); ?>" class="ts-field__label"><?php echo esc_html($label); ?></label>
            <div class="ts-field__body">
                <input class="regular-text ts-field__input<?php echo $locked ? ' ts-field__input--env-locked' : ''; ?>" type="url" id="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($field); ?>" value="<?php echo esc_attr($connection[$field]); ?>" <?php disabled($locked); ?>>
                <?php if ($locked) : ?><p class="ts-field__env-notice"><span aria-hidden="true">🔒</span> <?php esc_html_e('Set via constant — read only.', 'typesense-search'); ?></p><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php $prefixLocked = $network->constant('TYPESENSE_NETWORK_PREFIX') !== ''; ?>
        <div class="ts-field">
            <label for="prefix" class="ts-field__label"><?php esc_html_e('Index prefix (optional)', 'typesense-search'); ?></label>
            <div class="ts-field__body">
                <input class="regular-text ts-field__input<?php echo $prefixLocked ? ' ts-field__input--env-locked' : ''; ?>" type="text" id="prefix" name="prefix" value="<?php echo esc_attr($network->prefix()); ?>" <?php disabled($prefixLocked); ?>>
                <p class="ts-field__description"><?php esc_html_e('Prepended to every site collection name, e.g. eslov_. Lowercase letters, numbers, hyphens and underscores only.', 'typesense-search'); ?></p>
                <?php if ($prefixLocked) : ?><p class="ts-field__env-notice"><span aria-hidden="true">🔒</span> <?php esc_html_e('Set via constant — read only.', 'typesense-search'); ?></p><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="ts-settings__card">
    <div class="ts-settings__card-header">
        <h2><?php esc_html_e('API keys', 'typesense-search'); ?></h2>
        <p><?php esc_html_e('Two keys work together: one sets up new sites, the other keeps their content indexed.', 'typesense-search'); ?></p>
        <div class="ts-settings__notice ts-settings__notice--info">
            <ol>
                <li><?php esc_html_e('A provisioning key runs the automatic setup: saving the site selection on the Sites tab creates each site’s Typesense collection and its own search key.', 'typesense-search'); ?></li>
                <li><?php esc_html_e('The admin key below then indexes and maintains content in those collections. It only manages collections and documents — never other keys.', 'typesense-search'); ?></li>
            </ol>
        </div>
        <?php if ($provisioningAvailable) : ?>
        <div class="ts-settings__notice ts-settings__notice--success">
            <?php esc_html_e('Provisioning key configured: automatic setup can create and remove per-site search keys.', 'typesense-search'); ?>
        </div>
        <?php else : ?>
        <div class="ts-settings__notice ts-settings__notice--warning">
            <span>
            <?php
            printf(
                /* translators: %s is the constant name TYPESENSE_PROVISIONING_KEY */
                esc_html__('No provisioning key configured: automatic setup will fail at the key-creation step. Set %s (permanently, or for a single "wp ... typesense network setup" run) before saving the site selection on the Sites tab. See the security section in the README.', 'typesense-search'),
                '<code>TYPESENSE_PROVISIONING_KEY</code>'
            );
            ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
    <div class="ts-settings__fields">
        <?php $adminKeyLocked = $network->constant('TYPESENSE_ADMIN_KEY') !== ''; ?>
        <div class="ts-field">
            <label for="admin_key" class="ts-field__label"><?php esc_html_e('Admin key (indexing)', 'typesense-search'); ?></label>
            <div class="ts-field__body">
                <input class="regular-text ts-field__input<?php echo $adminKeyLocked ? ' ts-field__input--env-locked' : ''; ?>" type="password" id="admin_key" name="admin_key" autocomplete="new-password" value="" <?php disabled($adminKeyLocked); ?>>
                <p class="ts-field__description"><?php echo esc_html($connection['admin_key'] !== '' ? __('A key is configured. Leave blank to keep it.', 'typesense-search') : __('Enter the server admin API key.', 'typesense-search')); ?></p>
                <?php if ($adminKeyLocked) : ?><p class="ts-field__env-notice"><span aria-hidden="true">🔒</span> <?php esc_html_e('Set via constant — read only.', 'typesense-search'); ?></p><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$vectorModelLocked  = $network->constant('TYPESENSE_VECTOR_MODEL') !== '';
$networkVectorModel = $network->vectorModel();
$vectorPresets      = \TypesenseSearch\Typesense\EmbeddingModels::presets();
$isPresetVector     = array_key_exists($networkVectorModel, $vectorPresets);
?>
<div class="ts-settings__card">
    <div class="ts-settings__card-header">
        <h2><?php esc_html_e('Embedded search model', 'typesense-search'); ?></h2>
        <p><?php esc_html_e('Shared embedding model used whenever individual sites enable embedded (vector/hybrid) search. Keeping one model across the network guarantees cross-site multi-search compatibility and avoids loading duplicate models into Typesense memory.', 'typesense-search'); ?></p>
    </div>
    <div class="ts-settings__fields">
        <div class="ts-field">
            <label for="network-vector-model-choice" class="ts-field__label"><?php esc_html_e('Embedding model', 'typesense-search'); ?></label>
            <div class="ts-field__body">
                <?php if ($vectorModelLocked) : ?>
                    <input class="regular-text ts-field__input ts-field__input--env-locked" type="text" id="network-vector-model-choice" value="<?php echo esc_attr($networkVectorModel); ?>" disabled>
                    <p class="ts-field__env-notice"><span aria-hidden="true">🔒</span> <?php esc_html_e('Set via TYPESENSE_VECTOR_MODEL constant — read only.', 'typesense-search'); ?></p>
                <?php else : ?>
                    <select id="network-vector-model-choice" name="vector_model_choice" class="ts-field__input">
                        <?php foreach ($vectorPresets as $slug => $label) : ?>
                            <option value="<?php echo esc_attr($slug); ?>" <?php selected($isPresetVector ? $networkVectorModel : '', $slug); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="<?php echo esc_attr(\TypesenseSearch\Typesense\EmbeddingModels::CUSTOM_CHOICE); ?>" <?php selected(false, $isPresetVector); ?>>
                            <?php esc_html_e('Custom model…', 'typesense-search'); ?>
                        </option>
                    </select>
                    <input
                        class="regular-text ts-field__input"
                        type="text"
                        id="network-vector-model-custom"
                        name="vector_model_custom"
                        placeholder="ts/paraphrase-multilingual-mpnet-base-v2"
                        value="<?php echo esc_attr($isPresetVector ? '' : $networkVectorModel); ?>"
                        <?php echo $isPresetVector ? 'hidden' : ''; ?>
                    >
                    <p class="ts-field__description"><?php esc_html_e('Sites opt in to embedded search on their own Advanced settings page. Changing this model requires running "wp typesense rebuild" on each opted-in site.', 'typesense-search'); ?></p>
                    <script>
                    (function () {
                        var select = document.getElementById('network-vector-model-choice');
                        var custom = document.getElementById('network-vector-model-custom');
                        if (!select || !custom) return;
                        select.addEventListener('change', function () {
                            custom.hidden = select.value !== '<?php echo esc_js(\TypesenseSearch\Typesense\EmbeddingModels::CUSTOM_CHOICE); ?>';
                        });
                    })();
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</form>
<div class="typesense-network-actions">
<button type="submit" form="typesense-network-connection" class="button button-primary"><?php esc_html_e('Save changes', 'typesense-search'); ?></button>
<form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=typesense_network_save')); ?>">
<?php wp_nonce_field('typesense_network_save'); ?><input type="hidden" name="section" value="status"><button class="button"><?php esc_html_e('Check shared connection', 'typesense-search'); ?></button></form>
</div>

<?php elseif ($tab === 'multi-search') :
    $resolver       = new \TypesenseSearch\Multisite\MultiSearchResolver($network);
    $multiSearchMap = $network->multiSearchMap();
    $vectorModel    = $network->vectorModel();
    $enabledSites   = array_values(array_filter(
        $sites,
        static fn ($s): bool => in_array((int) $s->blog_id, $selected, true)
    ));
    $siteMeta = [];
    foreach ($enabledSites as $enabledSite) {
        $sid        = (int) $enabledSite->blog_id;
        $statusRow  = $this->siteStatus($sid, true, $network);
        $blogName   = trim((string) get_blog_option($sid, 'blogname', ''));
        $homeUrl    = (string) get_home_url($sid);
        $vecEnabled = (bool) get_blog_option($sid, \TypesenseSearch\Admin\Settings\OptionKeys::OPTION_VECTOR_ENABLED, 0);
        $vecReady   = $vecEnabled && $statusRow['ready'] && \TypesenseSearch\Typesense\SchemaInspector::isEmbeddingReady(
            $connection['remote'],
            $statusRow['collection'],
            $vectorModel,
            $sid
        );
        $siteMeta[$sid] = [
            'id'             => $sid,
            'name'           => $blogName !== '' ? $blogName : $homeUrl,
            'home'           => $homeUrl,
            'ready'          => $statusRow['ready'],
            'collection'     => $statusRow['collection'],
            'vector_enabled' => $vecEnabled,
            'vector_ready'   => $vecReady,
        ];
    }
?>
<?php if (isset($supportsUnionSearch) && !$supportsUnionSearch) : ?>
<div class="notice notice-error inline">
    <p><?php echo esc_html(sprintf(
        /* translators: %s: detected Typesense server version */
        __('Cross-site union multi-search requires Typesense v30.0 or newer (detected v%s). Upgrade Typesense before enabling multi-search.', 'typesense-search'),
        $serverVersionChecked ?? ''
    )); ?></p>
</div>
<?php endif; ?>

<div class="ts-settings__card">
    <div class="ts-settings__card-header">
        <h2><?php esc_html_e('Cross-site multi-search', 'typesense-search'); ?></h2>
        <p><?php esc_html_e('Choose which other enabled network sites each site searches alongside its own index. Only enabled, provisioned sites participate at query time.', 'typesense-search'); ?></p>
    </div>

    <?php if (count($enabledSites) < 2) : ?>
        <div class="ts-settings__fields">
            <p class="description">
                <?php esc_html_e('Enable and set up at least two sites on the Sites tab to configure cross-site multi-search.', 'typesense-search'); ?>
            </p>
        </div>
    <?php else : ?>
        <form id="typesense-multisearch" method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=typesense_network_save')); ?>">
            <?php wp_nonce_field('typesense_network_save'); ?>
            <input type="hidden" name="section" value="multi-search">
            <div class="ts-settings__fields">
                <?php foreach ($siteMeta as $sourceId => $sourceInfo) :
                    $configuredTargets = $multiSearchMap[$sourceId] ?? [];
                    $resolution        = $resolver->resolveForSite($sourceId);
                ?>
                <div class="ts-field">
                    <div class="ts-field__label">
                        <strong><?php echo esc_html($sourceInfo['name']); ?></strong>
                        <p class="description" style="margin: 4px 0 0; font-weight: 400;">
                            <?php echo esc_html($sourceInfo['home']); ?><br>
                            <?php if (!$sourceInfo['ready']) : ?>
                                <span class="typesense-network-state"><span aria-hidden="true" class="typesense-network-status typesense-network-status--pending"></span><?php esc_html_e('Waiting for site setup', 'typesense-search'); ?></span>
                            <?php elseif ($sourceInfo['vector_enabled'] && $sourceInfo['vector_ready']) : ?>
                                <span class="typesense-network-state"><span aria-hidden="true" class="typesense-network-status typesense-network-status--active"></span><?php esc_html_e('Embedded search ready', 'typesense-search'); ?></span>
                            <?php elseif ($sourceInfo['vector_enabled']) : ?>
                                <span class="typesense-network-state"><span aria-hidden="true" class="typesense-network-status typesense-network-status--pending"></span><?php esc_html_e('Embedded search: rebuild required', 'typesense-search'); ?></span>
                            <?php else : ?>
                                <span class="typesense-network-state"><span aria-hidden="true" class="typesense-network-status typesense-network-status--active"></span><?php esc_html_e('Keyword search ready', 'typesense-search'); ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="ts-field__body">
                        <fieldset <?php disabled(!$sourceInfo['ready']); ?>>
                            <legend class="screen-reader-text">
                                <?php echo esc_html(sprintf(/* translators: %s: source site name */ __('Additional sites searched from %s', 'typesense-search'), $sourceInfo['name'])); ?>
                            </legend>
                            <?php foreach ($siteMeta as $targetId => $targetInfo) :
                                if ($targetId === $sourceId) {
                                    continue;
                                }
                                $isChecked     = in_array($targetId, $configuredTargets, true);
                                $disableChoice = !$targetInfo['ready'] && !$isChecked;
                            ?>
                            <p style="margin: 0 0 8px;">
                                <label>
                                    <input
                                        type="checkbox"
                                        name="multisearch[<?php echo (int) $sourceId; ?>][]"
                                        value="<?php echo (int) $targetId; ?>"
                                        <?php checked($isChecked); ?>
                                        <?php disabled($disableChoice); ?>
                                    >
                                    <strong><?php echo esc_html($targetInfo['name']); ?></strong>
                                    <span class="description">(<?php echo esc_html($targetInfo['home']); ?>)</span>
                                    <?php if (!$targetInfo['ready']) : ?>
                                        &mdash; <em><?php esc_html_e('Not set up yet (skipped until setup completes)', 'typesense-search'); ?></em>
                                    <?php elseif ($targetInfo['vector_enabled'] && $targetInfo['vector_ready']) : ?>
                                        &mdash; <span class="description"><?php esc_html_e('Embedded index ready', 'typesense-search'); ?></span>
                                    <?php elseif ($targetInfo['vector_enabled']) : ?>
                                        &mdash; <em><?php esc_html_e('Embedded index needs rebuild', 'typesense-search'); ?></em>
                                    <?php else : ?>
                                        &mdash; <span class="description"><?php esc_html_e('Keyword index only', 'typesense-search'); ?></span>
                                    <?php endif; ?>
                                </label>
                            </p>
                            <?php endforeach; ?>
                        </fieldset>

                        <?php if ($resolution['enabled']) : ?>
                            <p class="ts-field__description">
                                <?php echo esc_html(sprintf(
                                    /* translators: %d: total number of collections searched */
                                    __('Active: searches %d collections (this site + selected ready sibling sites).', 'typesense-search'),
                                    count($resolution['collections'])
                                )); ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($resolution['vector_paused_reason'])) : ?>
                            <div class="notice notice-warning inline" style="margin: 8px 0 0;">
                                <p><?php echo esc_html($resolution['vector_paused_reason']); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($resolution['skipped_targets'])) : ?>
                            <div class="notice notice-warning inline" style="margin: 8px 0 0;">
                                <p>
                                    <?php
                                    $skippedSummaries = array_map(
                                        static fn (array $s): string => sprintf('%s (%s)', $s['site_name'], $s['reason']),
                                        $resolution['skipped_targets']
                                    );
                                    echo esc_html(sprintf(
                                        /* translators: %s: skipped target site list */
                                        __('Currently skipped target site(s): %s', 'typesense-search'),
                                        implode('; ', $skippedSummaries)
                                    ));
                                    ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php if (count($enabledSites) >= 2) : ?>
<p class="typesense-network-actions">
    <button type="submit" form="typesense-multisearch" class="button button-primary"><?php esc_html_e('Save multi-search settings', 'typesense-search'); ?></button>
</p>
<?php endif; ?>

<?php else : ?>
<div class="ts-settings__card">
    <div class="typesense-network-intro"><p><?php esc_html_e('Select sites and save to set up their indexes and search keys. Keep the page open until setup completes.', 'typesense-search'); ?></p><p><?php esc_html_e('Index content separately with WP-CLI. Disabling a site preserves its index and search key until you choose to delete them.', 'typesense-search'); ?></p></div>
<form id="typesense-sites" method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=typesense_network_save')); ?>">
<?php wp_nonce_field('typesense_network_save'); ?><input type="hidden" name="section" value="sites"></form>

<div class="typesense-network-table-wrap"><table class="widefat striped typesense-network-table"><colgroup><col class="typesense-col-selected"><col class="typesense-col-site"><col class="typesense-col-status"><col></colgroup><thead><tr><th scope="col"><?php esc_html_e('Selected', 'typesense-search'); ?></th><th scope="col"><?php esc_html_e('Site', 'typesense-search'); ?></th><th scope="col"><?php esc_html_e('Status', 'typesense-search'); ?></th><th scope="col"><?php esc_html_e('Index', 'typesense-search'); ?></th></tr></thead><tbody>
<?php foreach ($sites as $site) :
    $id = (int) $site->blog_id;
    $isSelected = in_array($id, $selected, true);
    $row = $this->siteStatus($id, $isSelected, $network);
?>
<tr><td><input aria-label="<?php echo esc_attr(sprintf(/* translators: %d: site ID */ __('Enable site %d', 'typesense-search'), $id)); ?>" type="checkbox" form="typesense-sites" name="sites[]" value="<?php echo $id; ?>" <?php checked($isSelected); ?> <?php disabled($tab !== 'sites'); ?>></td>
<td><a href="<?php echo esc_url(\TypesenseSearch\Multisite\SiteUrls::admin($id)); ?>"><?php echo esc_html(get_home_url($id)); ?></a></td>
<td><span class="typesense-network-state"><span aria-hidden="true" class="typesense-network-status typesense-network-status--<?php echo esc_attr($row['tone']); ?>"></span><strong><?php echo esc_html($row['label']); ?></strong></span>
<?php if ($row['detail'] !== '') : ?><p class="description"><?php echo esc_html($row['detail']); ?></p><?php endif; ?>
<?php if ($row['check']) : ?>
<p><?php echo esc_html(sprintf(/* translators: %s: date and time of the last check */ __('Last check: %s', 'typesense-search'), wp_date(get_option('date_format') . ' ' . get_option('time_format'), $row['check']['time']))); ?><br><?php echo esc_html(!empty($row['check']['success']) ? __('Server connection, admin key and site search key are working.', 'typesense-search') : __($row['check']['message'], 'typesense-search')); ?></p>
<?php elseif ($row['ready']) : ?><p class="description"><?php esc_html_e('Server and index have not been checked for this configuration.', 'typesense-search'); ?></p><?php endif; ?>

<?php if ($row['retry'] || $row['ready']) : ?>
<form class="typesense-network-site-actions" method="post" action="<?php echo esc_url(\TypesenseSearch\Multisite\SiteUrls::admin($id, 'admin-post.php')); ?>">
<input type="hidden" name="action" value="<?php echo esc_attr(NetworkSettingsPage::ACTION); ?>"><input type="hidden" name="site_id" value="<?php echo $id; ?>"><input type="hidden" name="network_id" value="<?php echo (int) $networkId; ?>"><input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">
<?php wp_nonce_field(NetworkSettingsPage::ACTION . '_' . $id); ?>
<?php if ($row['retry']) : ?><button class="button" name="operation" value="setup"><?php esc_html_e('Retry', 'typesense-search'); ?></button><?php endif; ?>
<?php if ($row['ready']) : ?><button class="button" name="operation" value="status"><?php esc_html_e('Check status', 'typesense-search'); ?></button><?php endif; ?>
<?php if (!empty($row['check']['missing'])) : ?><button class="button" name="operation" value="setup" title="<?php echo esc_attr__('Runs the setup again for this site: recreates a missing index and search key, then syncs the settings. Run indexing afterwards.', 'typesense-search'); ?>"><?php esc_html_e('Re-initialize', 'typesense-search'); ?></button><?php endif; ?>
</form>
<?php endif; ?>
</td>
<td><?php if ($row['collection'] !== '') : ?><code><?php echo esc_html($row['collection']); ?></code><p class="description"><?php echo esc_html(!empty($row['check']['success']) ? __('Index access verified at the last check.', 'typesense-search') : __('Saved index name. Existence has not been verified by a current status check.', 'typesense-search')); ?></p><?php else : ?>&ndash;<?php endif; ?>
<?php if ($isSelected) : ?>
<details><summary><?php esc_html_e('Indexing with WP-CLI', 'typesense-search'); ?></summary>
<p><?php esc_html_e('Use the same command for the first indexing run and later reindexing. Indexing results do not control activation; an empty or partially filled index can return incomplete results.', 'typesense-search'); ?></p>
<code><?php echo esc_html('wp --url=' . escapeshellarg(get_home_url($id)) . ' typesense index --yes'); ?></code>
</details>
<?php endif; ?>

<?php if ($row['deletable']) : ?>
<details><summary><?php esc_html_e('Delete index and search key', 'typesense-search'); ?></summary>
<form method="post" action="<?php echo esc_url(\TypesenseSearch\Multisite\SiteUrls::admin($id, 'admin-post.php')); ?>">
<input type="hidden" name="action" value="<?php echo esc_attr(NetworkSettingsPage::ACTION); ?>">
<input type="hidden" name="site_id" value="<?php echo $id; ?>"><input type="hidden" name="network_id" value="<?php echo (int) $networkId; ?>">
<input type="hidden" name="tab" value="sites"><input type="hidden" name="operation" value="delete">
<input type="hidden" name="delete_fingerprint" value="<?php echo esc_attr($row['delete_fingerprint']); ?>">
<?php wp_nonce_field(NetworkSettingsPage::ACTION . '_' . $id); ?>
<p><?php echo esc_html(sprintf(/* translators: %s: Typesense collection name */ __('Permanently delete index %s and its site search key. WordPress content is preserved. Re-enabling requires indexing again.', 'typesense-search'), $row['collection'])); ?></p>
<p><label><input type="checkbox" name="confirm_delete" value="1" required> <?php esc_html_e('I confirm deletion of this index and search key.', 'typesense-search'); ?></label></p>
<button class="button" type="submit"><?php esc_html_e('Delete index and search key', 'typesense-search'); ?></button>
</form></details>
<?php endif; ?>

<?php if ($row['resettable']) : ?>
<details><summary><?php esc_html_e('Reset saved index', 'typesense-search'); ?></summary>
<form method="post" action="<?php echo esc_url(\TypesenseSearch\Multisite\SiteUrls::admin($id, 'admin-post.php')); ?>">
<input type="hidden" name="action" value="<?php echo esc_attr(NetworkSettingsPage::ACTION); ?>">
<input type="hidden" name="site_id" value="<?php echo $id; ?>"><input type="hidden" name="network_id" value="<?php echo (int) $networkId; ?>">
<input type="hidden" name="tab" value="sites"><input type="hidden" name="operation" value="reset">
<?php wp_nonce_field(NetworkSettingsPage::ACTION . '_' . $id); ?>
<p><?php esc_html_e('Forget the saved index name and search key for this site, for example after a database migration or a changed network prefix. Typesense is not contacted: the old index and key remain on the server and must be removed there if they are no longer needed. The next setup creates a new index with the current prefix.', 'typesense-search'); ?></p>
<p><label><input type="checkbox" name="confirm_reset" value="1" required> <?php esc_html_e('I confirm the reset of the saved index for this site.', 'typesense-search'); ?></label></p>
<button class="button" type="submit"><?php esc_html_e('Reset saved index', 'typesense-search'); ?></button>
</form></details>
<?php endif; ?>
</td></tr>
<?php endforeach; ?>
<?php if (!$sites) : ?><tr><td colspan="4"><?php esc_html_e('No sites found.', 'typesense-search'); ?></td></tr><?php endif; ?>
</tbody></table></div>
</div>
<?php if ($tab === 'sites') : ?><p class="typesense-network-actions"><button type="submit" form="typesense-sites" class="button button-primary"><?php esc_html_e('Save site selection', 'typesense-search'); ?></button></p><?php endif; ?>
<?php endif; ?>
</div>
</div>
