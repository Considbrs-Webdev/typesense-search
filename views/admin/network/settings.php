<?php
use TypesenseSearch\Admin\NetworkSettingsPage;
use TypesenseSearch\Typesense\ProvisioningCredentials;
$provisioningAvailable = ProvisioningCredentials::isAvailableFor($connection['remote']);
?>
<style>
.typesense-network-heading { display: flex; align-items: center; flex-wrap: wrap; gap: 12px 20px; margin-bottom: 12px; }
.typesense-network-heading h1 { margin: 0; }
.typesense-network-environment { padding: 4px 9px; border: 1px solid #c3c4c7; border-radius: 3px; color: #50575e; font-size: 12px; white-space: nowrap; }
.typesense-network-intro { margin: 20px 0; max-width: 850px; }
.typesense-network-intro p { margin: 0 0 8px; font-weight: 400; }
.typesense-network-table-wrap { overflow-x: auto; }
.typesense-network-table { table-layout: fixed; width: 100%; min-width: 760px; }
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
</style>
<div class="wrap typesense-network">
<div class="typesense-network-heading"><h1><?php esc_html_e('Typesense Search — Network settings', 'typesense-search'); ?></h1><span class="typesense-network-environment"><?php echo esc_html(sprintf(__('Environment: %s', 'typesense-search'), wp_get_environment_type())); ?></span></div>
<nav class="nav-tab-wrapper">
<?php foreach (['connection' => __('Connection', 'typesense-search'), 'sites' => __('Sites', 'typesense-search')] as $slug => $label) : ?>
<a class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(network_admin_url('settings.php?page=typesense-network&tab=' . $slug)); ?>"><?php echo esc_html($label); ?></a>
<?php endforeach; ?>
</nav>
<?php $notice = get_site_transient('typesense_network_notice_' . get_current_user_id());
if (is_array($notice)) : delete_site_transient('typesense_network_notice_' . get_current_user_id()); ?>
<div class="notice <?php echo !empty($notice['success']) ? 'notice-success' : 'notice-error'; ?> inline"><p><?php echo esc_html(__($notice['message'], 'typesense-search')); ?></p></div>
<?php endif; ?>
<?php if (!empty($run)) : $nextId = (int) $run['sites'][0]; ?>
<div class="notice notice-info inline"><p><?php echo esc_html(sprintf(__('Setting up site %1$d of %2$d. Keep this page open until setup completes.', 'typesense-search'), $run['total'] - count($run['sites']) + 1, $run['total'])); ?></p>
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
<form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=typesense_network_save')); ?>">
<?php wp_nonce_field('typesense_network_save'); ?><input type="hidden" name="section" value="connection">
<table class="form-table" role="presentation">
<?php foreach (['remote' => [__('Typesense host', 'typesense-search'), 'TYPESENSE_HOST'], 'frontend_host' => [__('Frontend host (optional)', 'typesense-search'), 'TYPESENSE_FRONTEND_HOST']] as $field => [$label, $constant]) : ?>
<tr><th><label for="<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?></label></th><td><input class="regular-text" type="url" id="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($field); ?>" value="<?php echo esc_attr($connection[$field]); ?>" <?php disabled($network->constant($constant) !== ''); ?>><?php if ($network->constant($constant) !== '') : ?><p><?php esc_html_e('Set via constant.', 'typesense-search'); ?></p><?php endif; ?></td></tr>
<?php endforeach; ?>
<tr><th><label for="prefix"><?php esc_html_e('Index prefix (optional)', 'typesense-search'); ?></label></th><td><input class="regular-text" type="text" id="prefix" name="prefix" value="<?php echo esc_attr($network->prefix()); ?>" <?php disabled($network->constant('TYPESENSE_NETWORK_PREFIX') !== ''); ?>><p class="description"><?php esc_html_e('Prepended to every site collection name, e.g. eslov_. Lowercase letters, numbers, hyphens and underscores only.', 'typesense-search'); ?></p><?php if ($network->constant('TYPESENSE_NETWORK_PREFIX') !== '') : ?><p><?php esc_html_e('Set via constant.', 'typesense-search'); ?></p><?php endif; ?></td></tr>
<tr><th><label for="admin_key"><?php esc_html_e('Admin (indexing) API key', 'typesense-search'); ?></label></th><td><input class="regular-text" type="password" id="admin_key" name="admin_key" autocomplete="new-password" value="" <?php disabled($network->constant('TYPESENSE_ADMIN_KEY') !== ''); ?>><p class="description"><?php echo esc_html($connection['admin_key'] !== '' ? __('A key is configured. Leave blank to keep it.', 'typesense-search') : __('Enter the server admin API key.', 'typesense-search')); ?> <?php esc_html_e('Used only for collections and documents — never for key management. See the security section in the README.', 'typesense-search'); ?></p></td></tr>
</table>
<p class="description">
<?php if ($provisioningAvailable) : ?>
<?php esc_html_e('A provisioning key is configured: automatic setup can create and remove per-site search keys.', 'typesense-search'); ?>
<?php else : ?>
<?php esc_html_e('No provisioning key is configured: automatic setup will fail at the key-creation step. Configure TYPESENSE_PROVISIONING_KEY (permanently, or for a single "wp ... typesense network setup" run) before saving the site selection. See the security section in the README.', 'typesense-search'); ?>
<?php endif; ?>
</p>
<p><?php esc_html_e('Saving the connection starts automatic setup for selected sites. After changing a site URL or environment, save the site selection again. Existing indexes are preserved.', 'typesense-search'); ?></p>
<?php submit_button(); ?></form>
<form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=typesense_network_save')); ?>">
<?php wp_nonce_field('typesense_network_save'); ?><input type="hidden" name="section" value="status"><p><button class="button"><?php esc_html_e('Check shared connection', 'typesense-search'); ?></button></p></form>

<?php else : ?>
<form id="typesense-sites" method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=typesense_network_save')); ?>">
<?php wp_nonce_field('typesense_network_save'); ?><input type="hidden" name="section" value="sites"></form>
<div class="typesense-network-intro"><p><?php esc_html_e('Select sites and save to set up their indexes and search keys. Keep the page open until setup completes.', 'typesense-search'); ?></p><p class="description"><?php esc_html_e('Index content separately with WP-CLI. Disabling a site preserves its index and search key until you choose to delete them.', 'typesense-search'); ?></p></div>


<div class="typesense-network-table-wrap"><table class="widefat striped typesense-network-table"><colgroup><col class="typesense-col-selected"><col class="typesense-col-site"><col class="typesense-col-status"><col></colgroup><thead><tr><th scope="col"><?php esc_html_e('Selected', 'typesense-search'); ?></th><th scope="col"><?php esc_html_e('Site', 'typesense-search'); ?></th><th scope="col"><?php esc_html_e('Status', 'typesense-search'); ?></th><th scope="col"><?php esc_html_e('Index', 'typesense-search'); ?></th></tr></thead><tbody>
<?php foreach ($sites as $site) :
    $id = (int) $site->blog_id;
    $isSelected = in_array($id, $selected, true);
    $row = $this->siteStatus($id, $isSelected, $network);
?>
<tr><td><input aria-label="<?php echo esc_attr(sprintf(__('Enable site %d', 'typesense-search'), $id)); ?>" type="checkbox" form="typesense-sites" name="sites[]" value="<?php echo $id; ?>" <?php checked($isSelected); ?> <?php disabled($tab !== 'sites'); ?>></td>
<td><a href="<?php echo esc_url(\TypesenseSearch\Multisite\SiteUrls::admin($id)); ?>"><?php echo esc_html(get_home_url($id)); ?></a></td>
<td><span class="typesense-network-state"><span aria-hidden="true" class="typesense-network-status typesense-network-status--<?php echo esc_attr($row['tone']); ?>"></span><strong><?php echo esc_html($row['label']); ?></strong></span>
<?php if ($row['detail'] !== '') : ?><p class="description"><?php echo esc_html($row['detail']); ?></p><?php endif; ?>
<?php if ($row['check']) : ?>
<p><?php echo esc_html(sprintf(__('Last check: %s', 'typesense-search'), wp_date(get_option('date_format') . ' ' . get_option('time_format'), $row['check']['time']))); ?><br><?php echo esc_html(!empty($row['check']['success']) ? __('Server connection, admin key and site search key are working.', 'typesense-search') : __($row['check']['message'], 'typesense-search')); ?></p>
<?php elseif ($row['ready']) : ?><p class="description"><?php esc_html_e('Server and index have not been checked for this configuration.', 'typesense-search'); ?></p><?php endif; ?>

<?php if ($row['retry'] || $row['ready']) : ?>
<form class="typesense-network-site-actions" method="post" action="<?php echo esc_url(\TypesenseSearch\Multisite\SiteUrls::admin($id, 'admin-post.php')); ?>">
<input type="hidden" name="action" value="<?php echo esc_attr(NetworkSettingsPage::ACTION); ?>"><input type="hidden" name="site_id" value="<?php echo $id; ?>"><input type="hidden" name="network_id" value="<?php echo (int) $networkId; ?>"><input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">
<?php wp_nonce_field(NetworkSettingsPage::ACTION . '_' . $id); ?>
<?php if ($row['retry']) : ?><button class="button" name="operation" value="setup"><?php esc_html_e('Retry', 'typesense-search'); ?></button><?php endif; ?>
<?php if ($row['ready']) : ?><button class="button" name="operation" value="status"><?php esc_html_e('Check status', 'typesense-search'); ?></button><?php endif; ?>
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
<p><?php echo esc_html(sprintf(__('Permanently delete index %s and its site search key. WordPress content is preserved. Re-enabling requires indexing again.', 'typesense-search'), $row['collection'])); ?></p>
<p><label><input type="checkbox" name="confirm_delete" value="1" required> <?php esc_html_e('I confirm deletion of this index and search key.', 'typesense-search'); ?></label></p>
<button class="button" type="submit"><?php esc_html_e('Delete index and search key', 'typesense-search'); ?></button>
</form></details>
<?php endif; ?>
</td></tr>
<?php endforeach; ?>
<?php if (!$sites) : ?><tr><td colspan="4"><?php esc_html_e('No sites found.', 'typesense-search'); ?></td></tr><?php endif; ?>
</tbody></table></div>
<?php if ($tab === 'sites') : ?><p><button type="submit" form="typesense-sites" class="button button-primary"><?php esc_html_e('Save site selection', 'typesense-search'); ?></button></p><?php endif; ?>
<?php endif; ?>
</div>
