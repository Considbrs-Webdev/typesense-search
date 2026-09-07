<?php

namespace TypesenseSearch\Admin;

use TypesenseSearch\Multisite\{NetworkSettingsRepository, SiteProvisioner};
use TypesenseSearch\Typesense\ClientFactory;

/** Network-owned settings; site operations run through the target site's admin-post.php. */
class NetworkSettingsPage
{
    public const SLUG = 'typesense-network';
    public const ACTION = 'typesense_network_site';

    public function register(): void
    {
        add_action('network_admin_menu', function (): void {
            if (!(new NetworkSettingsRepository())->isNetworkActivated()) {
                return;
            }
            add_submenu_page('settings.php', __('Typesense Search', 'typesense-search'), __('Typesense Search', 'typesense-search'),
                'manage_network_options', self::SLUG, [$this, 'render']);
        });
        add_action('network_admin_edit_typesense_network_save', [$this, 'save']);
        add_action('admin_post_' . self::ACTION, [$this, 'siteAction']);
    }

    public function save(): void
    {
        $this->authorize();
        check_admin_referer('typesense_network_save');
        $network = new NetworkSettingsRepository();
        $networkId = $network->networkId();
        $section = sanitize_key($_POST['section'] ?? '');
        if (!in_array($section, ['connection', 'sites', 'status'], true)) {
            wp_die(esc_html__('Invalid settings section.', 'typesense-search'));
        }
        if ($section === 'status') {
            try {
                $connection = $network->connection();
                if ($connection['remote'] === '' || $connection['admin_key'] === '') {
                    throw new \RuntimeException('Configure the network connection first.');
                }
                $client = ClientFactory::build($connection['remote'], $connection['admin_key']);
                $health = $client->health->retrieve();
                $client->keys->retrieve();
                if (empty($health['ok'])) { throw new \RuntimeException('Server reports an unhealthy status.'); }
                $this->finish(__('Server connection and admin key are working.', 'typesense-search'), true);
            } catch (\Throwable $e) {
                $this->finish(__('Connection check failed. Verify the saved host and admin key.', 'typesense-search'), false);
            }
        }
        if ($section === 'connection') {
            $remote = esc_url_raw(wp_unslash($_POST['remote'] ?? ''), ['http', 'https']);
            $frontend = esc_url_raw(wp_unslash($_POST['frontend_host'] ?? ''), ['http', 'https']);
            foreach ([$remote, $frontend] as $url) {
                if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                    wp_die(esc_html__('Enter a valid HTTP or HTTPS URL.', 'typesense-search'));
                }
            }
            if ($network->constant('TYPESENSE_HOST') === '') {
                update_network_option($networkId, NetworkSettingsRepository::REMOTE, $remote);
            }
            if ($network->constant('TYPESENSE_FRONTEND_HOST') === '') {
                update_network_option($networkId, NetworkSettingsRepository::FRONTEND_HOST, $frontend);
            }
            $key = sanitize_text_field(wp_unslash($_POST['admin_key'] ?? ''));
            // Empty means keep the saved secret. It is never rendered into HTML.
            if ($key !== '' && $network->constant('TYPESENSE_ADMIN_KEY') === '') {
                update_network_option($networkId, NetworkSettingsRepository::ADMIN_KEY, $key);
            }
        } else {
            $ids = array_values(array_unique(array_map('absint', (array) ($_POST['sites'] ?? []))));
            foreach ($ids as $id) {
                $site = get_site($id);
                if (!$site || (int) $site->network_id !== $networkId || $site->archived || $site->spam || $site->deleted) {
                    wp_die(esc_html__('Invalid site selection.', 'typesense-search'));
                }
            }
            update_network_option($networkId, NetworkSettingsRepository::ENABLED, $ids);
        }
        wp_safe_redirect(network_admin_url('settings.php?page=' . self::SLUG . '&saved=1'));
        exit;
    }

    public function siteAction(): void
    {
        $this->authorize();
        $siteId = absint($_POST['site_id'] ?? 0);
        $networkId = absint($_POST['network_id'] ?? 0);
        $site = get_site($siteId);
        if (!$site || $siteId !== get_current_blog_id() || (int) $site->network_id !== $networkId || $networkId !== get_current_network_id()) {
            wp_die(esc_html__('Invalid site context.', 'typesense-search'), '', ['response' => 403]);
        }
        check_admin_referer(self::ACTION . '_' . $siteId);
        $operation = sanitize_key($_POST['operation'] ?? '');
        $success = true;
        try {
            $provisioner = new SiteProvisioner();
            if ($operation === 'prepare') {
                $provisioner->prepare();
                $message = __('Collection and search key prepared. Index the candidate before activating an existing site.', 'typesense-search');
            } elseif ($operation === 'activate') {
                if (empty($_POST['reviewed'])) {
                    throw new \RuntimeException('Confirm that the candidate content has been reviewed before activation.');
                }
                $provisioner->activate();
                $message = __('Typesense is now active for this site.', 'typesense-search');
            } elseif ($operation === 'status') {
                $network = new NetworkSettingsRepository();
                $connection = $network->connection();
                $client = ClientFactory::build($connection['remote'], $connection['admin_key']);
                $client->keys->retrieve();
                $mapping = $network->mapping();
                if (!$network->canUse()) {
                    throw new \RuntimeException('This site is disabled, unprepared, or its URL/environment/server has changed.');
                }
                (new \TypesenseSearch\Multisite\ProvisioningGateway())->verify($connection, $mapping);
                $message = __('Server connection, admin key and site search key are working.', 'typesense-search');
            } else {
                throw new \RuntimeException('Unknown operation.');
            }
        } catch (\Throwable $e) {
            $success = false;
            // Only our own actionable messages are displayed; SDK errors can contain credentials/URLs.
            $message = get_class($e) === \RuntimeException::class ? $e->getMessage()
                : __('Typesense operation failed. Verify the server, credentials and collection in Network Admin, then retry.', 'typesense-search');
        }
        $this->finish($message, $success);
    }

    private function finish(string $message, bool $success): void
    {
        set_site_transient('typesense_network_notice_' . get_current_user_id(), ['message' => $message, 'success' => $success], 120);
        wp_safe_redirect(network_admin_url('settings.php?page=' . self::SLUG . '&tab=sites'));
        exit;
    }

    private function authorize(): void
    {
        if (!current_user_can('manage_network_options') || !(new NetworkSettingsRepository())->isNetworkActivated()) {
            wp_die(esc_html__('Unauthorized.', 'typesense-search'), '', ['response' => 403]);
        }
    }

    public function render(): void
    {
        $this->authorize();
        $network = new NetworkSettingsRepository();
        $connection = $network->connection();
        $networkId = $network->networkId();
        $selected = array_map('intval', (array) get_network_option($networkId, NetworkSettingsRepository::ENABLED, []));
        $sites = get_sites(['network_id' => $networkId, 'number' => 0, 'archived' => 0, 'spam' => 0, 'deleted' => 0]);
        $tab = sanitize_key($_GET['tab'] ?? 'connection');
        include TYPESENSESEARCH_PATH . 'views/admin/network/settings.php';
    }
}
