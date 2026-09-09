<?php

namespace TypesenseSearch\Admin;

use TypesenseSearch\Multisite\{NetworkSettingsRepository, SiteProvisioner, SetupDispatcher};
use TypesenseSearch\Typesense\ClientFactory;

/** Network-owned settings; site operations run through the target site's admin-post.php. */
class NetworkSettingsPage
{
    public const SLUG = 'typesense-network';
    public const ACTION = 'typesense_network_site';
    public const CHECK = 'typesense_network_status_check';

    public function __construct(private SiteProvisioner $provisioner = new SiteProvisioner())
    {
    }

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
                    throw new \TypesenseSearch\Multisite\SetupException(__('Configure the network connection first.', 'typesense-search'));
                }
                $client = ClientFactory::build($connection['remote'], $connection['admin_key']);
                $health = $client->health->retrieve();
                $client->keys->retrieve();
                if (empty($health['ok'])) { throw new \TypesenseSearch\Multisite\SetupException(__('Server reports an unhealthy status.', 'typesense-search')); }
                $this->finish(__('Server connection and admin key are working.', 'typesense-search'), true, 'connection');
            } catch (\Throwable $e) {
                $this->finish(\TypesenseSearch\Multisite\SetupException::describe($e), false, 'connection');
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
        $runId = (new SetupDispatcher())->dispatch(
            (array) get_network_option($networkId, NetworkSettingsRepository::ENABLED, []), $section
        );
        wp_safe_redirect(network_admin_url('settings.php?page=' . self::SLUG . '&tab=' . $section . '&saved=1' . ($runId !== '' ? '&setup_run=' . rawurlencode($runId) : '')));
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
        $runId = is_string($_POST['setup_run'] ?? null) ? $_POST['setup_run'] : '';
        if ($runId !== '' && ($operation !== 'setup' || !SetupDispatcher::accepts($runId, $siteId))) {
            wp_die(esc_html__('This setup sequence has expired or changed. Save the site selection again.', 'typesense-search'), '', ['response' => 403]);
            return;
        }
        $success = true;
        $network = new NetworkSettingsRepository();
        $connection = $network->connection();
        $mapping = $network->mapping();
        try {
            $provisioner = $this->provisioner;
            if (in_array($operation, ['setup', 'prepare', 'activate'], true)) {
                $provisioner->setup();
                $message = __('Typesense is now active for this site.', 'typesense-search');
            } elseif ($operation === 'delete') {
                if (($_POST['confirm_delete'] ?? '') !== '1') {
                    throw new \TypesenseSearch\Multisite\SetupException(__('Confirm deletion of the index and search key first.', 'typesense-search'));
                }
                $provisioner->delete(is_string($_POST['delete_fingerprint'] ?? null) ? $_POST['delete_fingerprint'] : '');
                $message = __('The index and site search key were deleted. Re-enable the site and run indexing to restore its search content.', 'typesense-search');
            } elseif ($operation === 'status') {
                if (!$network->canUse()) {
                    throw new \TypesenseSearch\Multisite\SetupException($network->unavailableReason());
                }
                $client = ClientFactory::build($connection['remote'], $connection['admin_key']);
                $health = $client->health->retrieve();
                if (empty($health['ok'])) {
                    throw new \TypesenseSearch\Multisite\SetupException(__('The server reports an unhealthy status.', 'typesense-search'));
                }
                $client->keys->retrieve();
                (new \TypesenseSearch\Multisite\ProvisioningGateway())->verify($connection, $mapping);
                $message = __('Server connection, admin key and site search key are working.', 'typesense-search');
            } else {
                throw new \TypesenseSearch\Multisite\SetupException(__('Unknown operation.', 'typesense-search'));
            }
        } catch (\Throwable $e) {
            $success = false;
            // Only our own actionable messages are displayed; SDK errors can contain credentials/URLs.
            $message = \TypesenseSearch\Multisite\SetupException::describe($e);
        }
        if ($operation === 'status') {
            update_option(self::CHECK, [
                'fingerprint' => self::checkFingerprint($connection, $mapping),
                'time' => time(), 'success' => $success, 'message' => $message,
            ], false);
        }
        if ($runId !== '') {
            if (!$success) {
                update_option(SetupDispatcher::STATUS, ['status' => 'error', 'time' => time(), 'message' => $message], false);
            }
            try {
                SetupDispatcher::complete($runId, $siteId);
            } catch (\Throwable $e) {
                $success = false;
                $message = \TypesenseSearch\Multisite\SetupException::describe($e);
                $runId = ''; // Stop auto-submission if progress cannot be persisted.
            }
        }
        $this->finish($message, $success, self::tab($_POST['tab'] ?? 'sites'), $runId);
    }

    private function finish(string $message, bool $success, string $tab, string $runId = ''): void
    {
        set_site_transient('typesense_network_notice_' . get_current_user_id(), ['message' => $message, 'success' => $success], 120);
        // Permit only the network's own host when a mapped site returns to Network Admin.
        $returnUrl = network_admin_url('settings.php?page=' . self::SLUG . '&tab=' . self::tab($tab)
            . ($runId !== '' ? '&setup_run=' . rawurlencode($runId) : ''));
        $host = parse_url($returnUrl, PHP_URL_HOST);
        if ($host) {
            add_filter('allowed_redirect_hosts', static fn ($hosts) => array_merge($hosts, [$host]));
        }
        wp_safe_redirect($returnUrl);
        exit;
    }

    public static function tab(mixed $tab): string
    {
        // Old status links now lead to the site checks.
        return $tab === 'status' ? 'sites' : (in_array($tab, ['connection', 'sites'], true) ? $tab : 'connection');
    }

    /** Invalidate checks when credentials or the saved mapping change; never expose secrets. */
    public static function checkFingerprint(array $connection, array $mapping): string
    {
        return hash('sha256', serialize([$connection, $mapping]));
    }

    /** Read saved state only. Network rendering must not load sites or contact Typesense. */
    public function siteStatus(int $id, bool $selected, NetworkSettingsRepository $network): array
    {
        $connection = $network->connection();
        $state = (array) get_blog_option($id, NetworkSettingsRepository::STATE, []);
        $mapping = (array) ($state['active'] ?? []);
        $identity = ['home' => rtrim(get_home_url($id), '/'), 'environment' => wp_get_environment_type(),
            'remote' => rtrim($connection['remote'], '/'), 'network' => $network->networkId(), 'site' => $id];
        $ready = $selected && !$network->conflict() && ($mapping['identity'] ?? null) === $identity
            && !empty($mapping['collection']) && !empty($mapping['key'])
            && $connection['remote'] !== '' && $connection['admin_key'] !== '';
        $setup = SetupDispatcher::status($id);
        $busy = in_array($setup['status'] ?? '', ['pending', 'running'], true);
        $failed = ($setup['status'] ?? '') === 'error';
        $check = (array) get_blog_option($id, self::CHECK, []);
        if (!$ready || ($check['fingerprint'] ?? '') !== self::checkFingerprint($connection, $mapping)) {
            $check = [];
        }
        // A failed repeat setup must not hide a still-valid active configuration.
        $label = !$selected ? __('Disabled', 'typesense-search') : ($ready ? __('Active', 'typesense-search')
            : ($failed ? __('Setup failed', 'typesense-search') : __('Waiting for setup', 'typesense-search')));
        $tone = !$selected ? 'disabled' : ($ready ? 'active' : ($failed ? 'error' : 'pending'));
        $detail = $ready ? __('Setup is complete. Search uses Typesense when the server and index are available.', 'typesense-search')
            : ($selected ? __('WordPress search is used until setup is complete. Save the site selection to start setup.', 'typesense-search') : '');
        if ($selected && ($busy || $failed)) {
            $detail .= ' ' . ($failed ? (isset($setup['message']) ? __($setup['message'], 'typesense-search') : __('Setup failed. Retry setup.', 'typesense-search'))
                : (($setup['status'] ?? '') === 'pending'
                    ? __('Automatic setup has been requested, but has not started yet. Refresh this page to see the result.', 'typesense-search')
                    : __('Automatic setup is in progress. Refresh this page to see the result.', 'typesense-search')));
        }
        // A name saved before remote creation is not proof that the index exists.
        $saved = $mapping ?: (array) ($state['candidate'] ?? []);
        return ['ready' => $ready, 'label' => $label, 'tone' => $tone, 'detail' => trim($detail),
            'retry' => $selected && $failed && !$busy, 'check' => $check,
            'collection' => (string) ($saved['collection'] ?? ''),
            'deletable' => !$selected && !empty($saved['collection']) && !empty($saved['owner'])
                && ($saved['identity'] ?? null) === $identity && !$network->conflict(),
            'delete_fingerprint' => self::checkFingerprint($connection, $saved)];
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
        $tab = self::tab($_GET['tab'] ?? 'connection');
        $run = SetupDispatcher::pending(is_string($_GET['setup_run'] ?? null) ? $_GET['setup_run'] : '');
        include TYPESENSESEARCH_PATH . 'views/admin/network/settings.php';
    }
}
