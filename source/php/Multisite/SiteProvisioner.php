<?php

namespace TypesenseSearch\Multisite;

/** Operates in the target site's own WordPress request, after theme/plugin hooks load. */
class SiteProvisioner
{
    public function __construct(
        private NetworkSettingsRepository $network = new NetworkSettingsRepository(),
        private ProvisioningGateway $gateway = new ProvisioningGateway(),
    ) {
    }

    /** Complete setup without requiring an indexing run or manual activation.
     * Must run in the target site's own request, with its theme/plugin hooks loaded.
     */
    public function setup(): void
    {
        $this->locked(function (): void {
            update_option(SetupDispatcher::STATUS, ['status' => 'running', 'time' => time()], false);
            try {
                $this->prepareMapping();
                $this->activateMapping();
                update_option(SetupDispatcher::STATUS, ['status' => 'ready', 'time' => time()], false);
            } catch (\Throwable $e) {
                update_option(SetupDispatcher::STATUS, ['status' => 'error', 'time' => time(),
                    'message' => SetupException::describe($e)], false);
                throw $e;
            }
        });
    }

    /** Delete only the reviewed mapping for a disabled site, under the same setup/indexing lock. */
    public function delete(string $fingerprint): void
    {
        $this->locked(function () use ($fingerprint): void {
            $state = $this->network->state();
            $mapping = (array) (($state['active'] ?? []) ?: ($state['candidate'] ?? []));
            $connection = $this->network->connection();
            if (empty($mapping['collection']) || empty($mapping['owner'])
                || ($mapping['identity'] ?? null) !== $this->network->identity()
                || !hash_equals(\TypesenseSearch\Admin\NetworkSettingsPage::checkFingerprint($connection, $mapping), $fingerprint)) {
                throw new SetupException(__('The saved index or connection has changed. Refresh the page and check the site identity before deleting.', 'typesense-search'));
            }
            $name = $mapping['collection'];
            if ($this->gateway->exists($connection, $name) && !$this->gateway->owns($connection, $name, $mapping['owner'])) {
                throw new SetupException(__('The index does not belong to this site. Nothing was deleted.', 'typesense-search'));
            }
            $mappings = [];
            foreach (['active', 'candidate', 'previous'] as $slot) {
                $saved = (array) ($state[$slot] ?? []);
                if (($saved['identity'] ?? null) === $mapping['identity'] && ($saved['collection'] ?? '') === $name) {
                    if (($saved['owner'] ?? '') !== $mapping['owner']) {
                        throw new SetupException(__('The index does not belong to this site. Nothing was deleted.', 'typesense-search'));
                    }
                    $mappings[$slot] = $saved;
                }
            }
            // Resolve every key before mutating anything. Keep state on failure so deletion can be retried.
            foreach ($this->gateway->deletionKeyIds($connection, $mappings) as $id) {
                $this->gateway->deleteKey($connection, $id);
            }
            $this->gateway->deleteCollection($connection, $name);
            foreach (array_keys($mappings) as $slot) {
                unset($state[$slot]);
            }
            $this->save($state);
            delete_option(SetupDispatcher::JOB);
            delete_option(SetupDispatcher::STATUS);
            delete_option(\TypesenseSearch\Admin\NetworkSettingsPage::CHECK);
        }, true);
    }

    public function prepare(): void
    {
        $this->locked(fn () => $this->prepareMapping());
    }

    private function prepareMapping(): void
    {
        $state = $this->network->state();
        $identity = $this->network->identity();
        $name = (new CollectionNameResolver())->resolve($this->network->prefix());
        $candidate = ['identity' => $identity, 'collection' => $name, 'key' => ''];
        // Returning to a previously configured server/environment must reuse
        // its ownership proof, even if a different candidate was prepared.
        foreach (['candidate', 'active', 'previous'] as $slot) {
            $saved = (array) ($state[$slot] ?? []);
            if (($saved['identity'] ?? null) === $identity && ($saved['collection'] ?? '') === $name) {
                $candidate = $saved;
                break;
            }
        }
        $connection = $this->network->connection();
        $exists = $this->gateway->exists($connection, $name);
        if ($exists && (empty($candidate['owner']) || !$this->gateway->owns($connection, $name, $candidate['owner']))) {
            throw new \TypesenseSearch\Multisite\SetupException(__('The collection already exists but is not owned by this provisioning state. Choose an unused target or review it manually.', 'typesense-search'));
        }
        if (!$exists) {
            // Persist intent before the remote operation, so a key failure can be retried.
            $candidate['owner'] = $candidate['owner'] ?? bin2hex(random_bytes(16));
            $candidate['key'] = '';
            $state['candidate'] = $candidate;
            $this->save($state);
            // A temporary key only unlocks internal settings for schema/capability resolution.
            $schemaContext = $candidate;
            $schemaContext['key'] = 'provisioning';
            $this->network->withCandidate($schemaContext, fn () => $this->gateway->create($connection, $name));
        }
        if (!empty($candidate['key'])) {
            try {
                $this->gateway->verify($connection, $candidate);
            } catch (\Typesense\Exceptions\RequestUnauthorized $e) {
                $candidate['key'] = ''; // Explicit retry repairs a revoked key.
            }
        }
        if (empty($candidate['key'])) {
            $candidate['key'] = $this->gateway->key($connection, $name);
            $state['candidate'] = $candidate;
            $this->save($state);
        }
        $this->gateway->verify($connection, $candidate);
        $candidate['prepared'] = true;
        $state['candidate'] = $candidate;
        $this->save($state);
    }

    /** Explicit CLI candidate indexing; active frontend mapping is unchanged. */
    public function index(callable $operation): void
    {
        $this->locked(function () use ($operation): void {
            $candidate = $this->candidate();
            $this->network->withCandidate($candidate, $operation);
        });
    }

    public function activate(): void
    {
        $this->locked(fn () => $this->activateMapping());
    }

    private function activateMapping(): void
    {
        $candidate = $this->candidate();
        $this->gateway->verify($this->network->connection(), $candidate);
        $this->network->withCandidate($candidate, fn () => $this->gateway->sync());
        $state = $this->network->state();
        if (($state['active'] ?? null) !== $candidate) {
            $state['previous'] = $state['active'] ?? [];
        }
        // Do not overwrite legacy local options: local activation can safely
        // resume its original connection/collection/key after network mode ends.
        $state['active'] = $candidate;
        $this->save($state); // One authoritative option switches both values together.
    }

    private function candidate(): array
    {
        $candidate = (array) ($this->network->state()['candidate'] ?? []);
        if (empty($candidate['prepared']) || empty($candidate['key']) || ($candidate['identity'] ?? null) !== $this->network->identity()) {
            throw new \TypesenseSearch\Multisite\SetupException(__('Setup is incomplete for the current URL, environment and server. Save the site selection to retry setup.', 'typesense-search'));
        }
        if (empty($candidate['owner']) || !$this->gateway->owns($this->network->connection(), $candidate['collection'], $candidate['owner'])) {
            throw new \TypesenseSearch\Multisite\SetupException(__('The index no longer belongs to this site. Check the index ownership before retrying setup.', 'typesense-search'));
        }
        return $candidate;
    }

    private function save(array $state): void
    {
        update_option(NetworkSettingsRepository::STATE, $state, false);
        if (get_option(NetworkSettingsRepository::STATE) !== $state) {
            throw new \TypesenseSearch\Multisite\SetupException(__('Could not save provisioning state.', 'typesense-search'));
        }
    }

    private function locked(callable $operation, bool $deleting = false): void
    {
        if ($deleting && $this->network->selected()) {
            throw new SetupException(__('Disable the site and save the site selection before deleting its index and search key.', 'typesense-search'));
        }
        if (!$this->network->isNetworkActivated() || (!$deleting && !$this->network->selected()) || $this->network->conflict()) {
            throw new \TypesenseSearch\Multisite\SetupException(__('Select this site in Network Admin and remove global collection/search-key constants first.', 'typesense-search'));
        }
        $connection = $this->network->connection();
        if ($connection['remote'] === '' || $connection['admin_key'] === '') {
            throw new \TypesenseSearch\Multisite\SetupException(__('Configure the network connection first.', 'typesense-search'));
        }
        // No automatic lock stealing: a long running indexing process may still own it.
        $token = bin2hex(random_bytes(16));
        if (!add_option(NetworkSettingsRepository::LOCK, $token, '', false)) {
            throw new \TypesenseSearch\Multisite\SetupException(__('Another provisioning operation holds this site lock. If its process has ended, remove typesense_network_provision_lock with WP-CLI and retry.', 'typesense-search'));
        }
        $siteId = get_current_blog_id();
        // WP-CLI::error exits instead of throwing; release our own lock on exit too.
        $release = static function () use ($siteId, $token): void {
            if (get_blog_option($siteId, NetworkSettingsRepository::LOCK) === $token) {
                delete_blog_option($siteId, NetworkSettingsRepository::LOCK);
            }
        };
        if (defined('WPINC')) {
            register_shutdown_function($release);
        }
        try {
            $operation();
        } finally {
            $release();
        }
    }
}
