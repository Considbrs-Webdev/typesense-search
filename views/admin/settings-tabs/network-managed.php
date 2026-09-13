<div class="notice notice-info inline"><p>
<?php esc_html_e('The Typesense connection and site activation are managed by the network administrator.', 'typesense-search'); ?>
<?php if (current_user_can('manage_network_options')) : ?>
<a href="<?php echo esc_url(network_admin_url('settings.php?page=typesense-network')); ?>"><?php esc_html_e('Network settings', 'typesense-search'); ?></a>
<?php endif; ?>
</p></div>
