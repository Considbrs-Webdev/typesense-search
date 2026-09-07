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

    public function prepare(): void
    {
        $this->locked(function (): void {
            $state = $this->network->state();
            $identity = $this->network->identity();
            $name = (new CollectionNameResolver())->resolve();
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
                throw new \RuntimeException('The collection already exists but is not owned by this provisioning state. Choose an unused target or review it manually.');
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
        });
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
        $this->locked(function (): void {
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
        });
    }

    private function candidate(): array
    {
        $candidate = (array) ($this->network->state()['candidate'] ?? []);
        if (empty($candidate['prepared']) || empty($candidate['key']) || ($candidate['identity'] ?? null) !== $this->network->identity()) {
            throw new \RuntimeException('Prepare this site for the current URL, environment and server first.');
        }
        if (empty($candidate['owner']) || !$this->gateway->owns($this->network->connection(), $candidate['collection'], $candidate['owner'])) {
            throw new \RuntimeException('The prepared collection no longer belongs to this site. Prepare and review it again.');
        }
        return $candidate;
    }

    private function save(array $state): void
    {
        update_option(NetworkSettingsRepository::STATE, $state, false);
        if (get_option(NetworkSettingsRepository::STATE) !== $state) {
            throw new \RuntimeException('Could not save provisioning state.');
        }
    }

    private function locked(callable $operation): void
    {
        if (!$this->network->isNetworkActivated() || !$this->network->selected() || $this->network->conflict()) {
            throw new \RuntimeException('Select this site in Network Admin and remove global collection/search-key constants first.');
        }
        $connection = $this->network->connection();
        if ($connection['remote'] === '' || $connection['admin_key'] === '') {
            throw new \RuntimeException('Configure the network connection first.');
        }
        // No automatic lock stealing: a long running indexing process may still own it.
        $token = bin2hex(random_bytes(16));
        if (!add_option(NetworkSettingsRepository::LOCK, $token, '', false)) {
            throw new \RuntimeException('Another provisioning operation holds this site lock. If its process has ended, remove typesense_network_provision_lock with WP-CLI and retry.');
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
