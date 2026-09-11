<?php

namespace TypesenseSearch\Multisite;

/** A per-administrator sequence of authenticated, top-level browser POSTs. */
class SetupDispatcher
{
    public const JOB = 'typesense_network_setup_job'; // Legacy jobs are removed when a new run is saved.
    public const STATUS = 'typesense_network_setup_status';
    public const RUN = 'typesense_network_setup_run_';

    private static function key(): string
    {
        return self::RUN . get_current_user_id();
    }

    public function dispatch(array $siteIds, string $tab = 'sites'): string
    {
        $network = new NetworkSettingsRepository();
        if (!current_user_can('manage_network_options') || !$network->isNetworkActivated()) {
            throw new SetupException(__('Unauthorized setup request.', 'typesense-search'));
        }
        $ids = [];
        $selected = array_map('intval', (array) get_network_option($network->networkId(), NetworkSettingsRepository::ENABLED, []));
        foreach (array_unique(array_map('intval', $siteIds)) as $id) {
            $site = get_site($id);
            if (!$site || (int) $site->network_id !== $network->networkId() || $site->archived || $site->spam || $site->deleted
                || !in_array($id, $selected, true)) {
                continue;
            }
            $ids[] = $id;
            delete_blog_option($id, self::JOB);
        }
        if (!$ids) {
            delete_site_transient(self::key());
            return '';
        }
        $run = ['id' => bin2hex(random_bytes(16)), 'sites' => $ids, 'total' => count($ids),
            'network' => $network->networkId(), 'tab' => $tab === 'connection' ? 'connection' : 'sites'];
        if (!set_site_transient(self::key(), $run, 3600)) {
            throw new SetupException(__('Could not save the setup sequence. Save the site selection again.', 'typesense-search'));
        }
        return $run['id'];
    }

    public static function pending(string $id): array
    {
        $run = get_site_transient(self::key());
        return is_array($run) && $id !== '' && hash_equals((string) ($run['id'] ?? ''), $id)
            && ($run['network'] ?? 0) === (new NetworkSettingsRepository())->networkId() ? $run : [];
    }

    public static function accepts(string $id, int $siteId): bool
    {
        $run = self::pending($id);
        return $run && ($run['sites'][0] ?? 0) === $siteId;
    }

    public static function complete(string $id, int $siteId): void
    {
        $run = self::pending($id);
        if (!$run || ($run['sites'][0] ?? 0) !== $siteId) {
            return;
        }
        array_shift($run['sites']);
        if ($run['sites']) {
            if (!set_site_transient(self::key(), $run, 3600)) {
                throw new SetupException(__('Could not save the setup sequence. Save the site selection again.', 'typesense-search'));
            }
        } else {
            delete_site_transient(self::key());
        }
    }

    public static function status(int $siteId): array
    {
        $status = (array) get_blog_option($siteId, self::STATUS, []);
        // Old unauthenticated workers are no longer registered; a pending legacy job cannot start.
        if (($status['status'] ?? '') === 'pending'
            || (($status['status'] ?? '') === 'running' && ($status['time'] ?? 0) < time() - 300)) {
            $status['status'] = 'error';
            $status['message'] = __('Setup did not finish. Save the site selection again and keep the page open until setup completes. If a lock remains, confirm the old process has ended before removing it.', 'typesense-search');
        }
        return $status;
    }
}
