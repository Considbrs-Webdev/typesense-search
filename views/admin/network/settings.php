<?php
use TypesenseSearch\Admin\NetworkSettingsPage;
use TypesenseSearch\Multisite\NetworkSettingsRepository;
?>
<div class="wrap">
<h1><?php esc_html_e('Typesense Search — Network settings', 'typesense-search'); ?></h1>
<nav class="nav-tab-wrapper">
<?php foreach (['connection' => __('Connection', 'typesense-search'), 'sites' => __('Sites', 'typesense-search'), 'status' => __('Status', 'typesense-search')] as $slug => $label) : ?>
<a class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(network_admin_url('settings.php?page=typesense-network&tab=' . $slug)); ?>"><?php echo esc_html($label); ?></a>
<?php endforeach; ?>
</nav>
<?php $notice = get_site_transient('typesense_network_notice_' . get_current_user_id());
if (is_array($notice)) : delete_site_transient('typesense_network_notice_' . get_current_user_id()); ?>
<div class="notice <?php echo !empty($notice['success']) ? 'notice-success' : 'notice-error'; ?> inline"><p><?php echo esc_html($notice['message']); ?></p></div>
<?php endif; ?>
<?php if ($network->conflict()) : ?>
<div class="notice notice-error inline"><p><?php esc_html_e('Remove TYPESENSE_COLLECTION and TYPESENSE_SEARCH_KEY constants before using network mode. Each site needs its own collection and key.', 'typesense-search'); ?></p></div>
<?php endif; ?>
<?php if (isset($_GET['saved'])) : ?><div class="notice notice-success inline"><p><?php esc_html_e('Settings saved.', 'typesense-search'); ?></p></div><?php endif; ?>
<p><?php echo esc_html(sprintf(__('Environment: %s. New sites are disabled until selected, prepared and activated.', 'typesense-search'), wp_get_environment_type())); ?></p>
<?php if ($tab === 'connection') : ?>
<form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=typesense_network_save')); ?>">
<?php wp_nonce_field('typesense_network_save'); ?><input type="hidden" name="section" value="connection">
<table class="form-table" role="presentation">
<?php foreach (['remote' => [__('Typesense host', 'typesense-search'), 'TYPESENSE_HOST'], 'frontend_host' => [__('Frontend host (optional)', 'typesense-search'), 'TYPESENSE_FRONTEND_HOST']] as $field => [$label, $constant]) : ?>
<tr><th><label for="<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?></label></th><td><input class="regular-text" type="url" id="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($field); ?>" value="<?php echo esc_attr($connection[$field]); ?>" <?php disabled($network->constant($constant) !== ''); ?>><?php if ($network->constant($constant) !== '') : ?><p><?php esc_html_e('Set via constant.', 'typesense-search'); ?></p><?php endif; ?></td></tr>
<?php endforeach; ?>
<tr><th><label for="admin_key"><?php esc_html_e('Admin API key', 'typesense-search'); ?></label></th><td><input class="regular-text" type="password" id="admin_key" name="admin_key" autocomplete="new-password" value="" <?php disabled($network->constant('TYPESENSE_ADMIN_KEY') !== ''); ?>><p class="description"><?php echo esc_html($connection['admin_key'] !== '' ? __('A key is configured. Leave blank to keep it.', 'typesense-search') : __('Enter the server admin API key.', 'typesense-search')); ?></p></td></tr>
</table>
<p><?php esc_html_e('Changing the host, site URL or environment requires preparing and reviewing the affected sites again. Existing collections are preserved.', 'typesense-search'); ?></p>
<?php submit_button(); ?></form>
<?php else : ?>
<?php if ($tab === 'sites') : ?>
<form id="typesense-sites" method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=typesense_network_save')); ?>">
<?php wp_nonce_field('typesense_network_save'); ?><input type="hidden" name="section" value="sites"></form>
<p><?php esc_html_e('Save site selection first. Prepare creates or reuses a separate collection and key. Index the candidate with WP-CLI, review its results, then activate. Disabling preserves existing indexes and keys.', 'typesense-search'); ?></p>
<?php else : ?>
<form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=typesense_network_save')); ?>">
<?php wp_nonce_field('typesense_network_save'); ?><input type="hidden" name="section" value="status"><p><button class="button"><?php esc_html_e('Check shared connection', 'typesense-search'); ?></button></p></form>
<p><?php esc_html_e('Checks run only when requested. Each check verifies the shared server credentials and that site’s active search key.', 'typesense-search'); ?></p>
<?php endif; ?>
<table class="widefat striped"><thead><tr><th><?php esc_html_e('Enabled', 'typesense-search'); ?></th><th><?php esc_html_e('Site', 'typesense-search'); ?></th><th><?php esc_html_e('Collection / state', 'typesense-search'); ?></th><th><?php esc_html_e('Actions', 'typesense-search'); ?></th></tr></thead><tbody>
<?php foreach ($sites as $site) :
    $id = (int) $site->blog_id;
    $state = (array) get_blog_option($id, NetworkSettingsRepository::STATE, []);
    $mapping = (array) ($state['active'] ?? []);
    $identity = ['home' => rtrim(get_home_url($id), '/'), 'environment' => wp_get_environment_type(), 'remote' => rtrim($connection['remote'], '/'), 'network' => $networkId, 'site' => $id];
    $ready = in_array($id, $selected, true) && !$network->conflict() && ($mapping['identity'] ?? null) === $identity && !empty($mapping['key']) && $connection['remote'] !== '' && $connection['admin_key'] !== '';
    $statusLabel = !in_array($id, $selected, true) ? __('Disabled', 'typesense-search') : ($ready ? __('Active', 'typesense-search') : __('Preparation or activation required', 'typesense-search'));
?>
<tr><td><input aria-label="<?php echo esc_attr(sprintf(__('Enable site %d', 'typesense-search'), $id)); ?>" type="checkbox" form="typesense-sites" name="sites[]" value="<?php echo $id; ?>" <?php checked(in_array($id, $selected, true)); ?> <?php disabled($tab !== 'sites'); ?>></td>
<td><a href="<?php echo esc_url(\TypesenseSearch\Multisite\SiteUrls::admin($id)); ?>"><?php echo esc_html(get_home_url($id)); ?></a></td>
<td><strong><?php echo esc_html($statusLabel); ?></strong><br><code><?php echo esc_html($mapping['collection'] ?? __('Not active', 'typesense-search')); ?></code>
<?php if (isset($state['candidate'])) : ?><p><?php echo esc_html(sprintf(__('Candidate: %s', 'typesense-search'), $state['candidate']['collection'] ?? '')); ?></p><?php endif; ?></td>
<td><form method="post" action="<?php echo esc_url(\TypesenseSearch\Multisite\SiteUrls::admin($id, 'admin-post.php')); ?>">
<input type="hidden" name="action" value="<?php echo esc_attr(NetworkSettingsPage::ACTION); ?>"><input type="hidden" name="site_id" value="<?php echo $id; ?>"><input type="hidden" name="network_id" value="<?php echo (int) $networkId; ?>">
<?php wp_nonce_field(NetworkSettingsPage::ACTION . '_' . $id); ?>
<?php if ($tab === 'sites') : ?>
<button class="button" name="operation" value="prepare"><?php esc_html_e('Prepare / retry', 'typesense-search'); ?></button>
<p><code><?php echo esc_html('wp --url=' . get_home_url($id) . ' typesense network index --yes'); ?></code></p>
<label><input type="checkbox" name="reviewed" value="1"> <?php esc_html_e('I have reviewed the candidate content.', 'typesense-search'); ?></label>
<button class="button" name="operation" value="activate"><?php esc_html_e('Activate', 'typesense-search'); ?></button>
<?php else : ?><button class="button" name="operation" value="status"><?php esc_html_e('Check status', 'typesense-search'); ?></button><?php endif; ?>
</form></td></tr>
<?php endforeach; ?></tbody></table>
<?php if ($tab === 'sites') : ?><p><button type="submit" form="typesense-sites" class="button button-primary"><?php esc_html_e('Save site selection', 'typesense-search'); ?></button></p><?php endif; ?>
<?php endif; ?>
</div>
