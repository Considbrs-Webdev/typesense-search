<?php

namespace TypesenseSearch\Multisite;

use Typesense\Exceptions\ObjectNotFound;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Typesense\{AdminApi, ApiKey, ClientFactory, Collection, ProvisioningClientFactory, ServerCapabilities};

/** Typesense operations used by provisioning; replaceable in failure-path tests. */
class ProvisioningGateway
{
    /** Set by key() as a side effect; null when a test double overrides key() directly. */
    private ?string $lastKeyId = null;

    public function exists(array $connection, string $name): bool
    {
        try {
            ClientFactory::build($connection['remote'], $connection['admin_key'])->collections[$name]->retrieve();
            return true;
        } catch (ObjectNotFound $e) {
            return false;
        }
    }

    public function owns(array $connection, string $name, string $owner): bool
    {
        $schema = ClientFactory::build($connection['remote'], $connection['admin_key'])->collections[$name]->retrieve();
        return ($schema['metadata']['typesense_search_owner'] ?? null) === $owner;
    }

    public function create(array $connection, string $name): void
    {
        $settings = new SettingsRepository();
        $schema = Collection::getSchema($name, $settings, new ServerCapabilities(new AdminApi($settings)));
        // Those sets do not exist on a fresh server yet. Activation sync creates
        // them and attaches them before the candidate becomes the active index.
        unset($schema['synonym_sets'], $schema['curation_sets']);
        $schema['name'] = $name;
        $schema['metadata']['typesense_search_owner'] = (new NetworkSettingsRepository())->mapping()['owner'];
        ClientFactory::build($connection['remote'], $connection['admin_key'])->collections->create($schema);
    }

    /**
     * Create a scoped search key via the provisioning client. The server-side
     * key ID is captured as a side effect, retrievable via lastKeyId() — kept
     * out of the return type so existing overrides of this method (typed to
     * return a bare string) keep working unchanged.
     */
    public function key(array $connection, string $name): string
    {
        $result = ApiKey::generateSearchKeyWithId(ProvisioningClientFactory::fromTrustedRemote($connection['remote']), $name);
        $this->lastKeyId = $result['id'];
        return $result['value'];
    }

    /** The ID of the key created by the most recent key() call, when known. */
    public function lastKeyId(): ?string
    {
        return $this->lastKeyId;
    }

    /** Resolve legacy keys by their prefix and exact search-only scope; never guess on collisions. */
    public function deletionKeyIds(array $connection, array $mappings): array
    {
        $keys = ProvisioningClientFactory::fromTrustedRemote($connection['remote'])->keys->retrieve()['keys'] ?? [];
        return self::matchDeletionKeys($keys, $mappings);
    }

    public static function matchDeletionKeys(array $keys, array $mappings): array
    {
        $ids = [];
        foreach ($mappings as $mapping) {
            if (empty($mapping['key'])) {
                continue;
            }
            // A stored key_id is an exact, unambiguous reference — skip the heuristic entirely.
            if (!empty($mapping['key_id'])) {
                if (array_filter($keys, static fn ($key) => (string) ($key['id'] ?? '') === (string) $mapping['key_id'])) {
                    $ids[] = (string) $mapping['key_id'];
                }
                continue;
            }
            $matches = array_values(array_filter($keys, static fn ($key) =>
                strlen((string) ($key['value_prefix'] ?? '')) >= 4
                && str_starts_with($mapping['key'], $key['value_prefix'])
                && ($key['collections'] ?? []) === [$mapping['collection']]
                && ($key['actions'] ?? []) === ['documents:search']
                && ($key['description'] ?? '') === 'Search-only key for collection: ' . $mapping['collection']));
            $prefixMatches = array_filter($keys, static fn ($key) =>
                strlen((string) ($key['value_prefix'] ?? '')) >= 4 && str_starts_with($mapping['key'], $key['value_prefix']));
            if (count($matches) > 1 || count($prefixMatches) !== count($matches)) {
                throw new SetupException(__('The search key cannot be identified uniquely. No resources were deleted. Check the keys on the server.', 'typesense-search'));
            }
            if ($matches) {
                $ids[] = (string) $matches[0]['id'];
            }
        }
        return array_values(array_unique($ids));
    }

    public function deleteKey(array $connection, string $id): void
    {
        try {
            ProvisioningClientFactory::fromTrustedRemote($connection['remote'])->keys[$id]->delete();
        } catch (ObjectNotFound $e) {
            // A retry can encounter a key already removed by the preceding attempt.
        }
    }

    /**
     * Clean up a key orphaned by a crash between remote key creation and local
     * state persistence (see SiteProvisioner::prepareMapping()). Only acts
     * when exactly one candidate is unambiguous; otherwise leaves keys alone
     * rather than guessing — a known, documented gap, not a promise of full
     * idempotency.
     */
    public function revokeOrphanedKey(array $connection, string $collectionName): void
    {
        $keys = ProvisioningClientFactory::fromTrustedRemote($connection['remote'])->keys->retrieve()['keys'] ?? [];
        $candidates = array_values(array_filter($keys, static fn ($key) =>
            ($key['collections'] ?? []) === [$collectionName]
            && ($key['actions'] ?? []) === ['documents:search']
            && ($key['description'] ?? '') === 'Search-only key for collection: ' . $collectionName));
        if (count($candidates) === 1) {
            $this->deleteKey($connection, (string) $candidates[0]['id']);
        }
    }

    public function deleteCollection(array $connection, string $name): void
    {
        try {
            ClientFactory::build($connection['remote'], $connection['admin_key'])->collections[$name]->delete();
        } catch (ObjectNotFound $e) {
            // Already removed is the intended result.
        }
    }

    public function verify(array $connection, array $mapping): void
    {
        ClientFactory::build($connection['remote'], $mapping['key'])->collections[$mapping['collection']]->documents->search([
            'q' => '*', 'query_by' => 'title', 'per_page' => 0,
        ]);
    }

    public function sync(): void
    {
        $settings = new SettingsRepository();
        $api = new AdminApi($settings);
        if ($settings->isSynonymsEnabled()) {
            $result = (new \TypesenseSearch\Synonyms\TypesenseSync($settings, $api))->sync((new \TypesenseSearch\Synonyms\Repository())->all());
            if (!$result['ok']) {
                throw new \TypesenseSearch\Multisite\SetupException(__('Synonym synchronization failed.', 'typesense-search'));
            }
        }
        if ($settings->isPinnedResultsEnabled()) {
            $result = (new \TypesenseSearch\PinnedResults\TypesenseSync($settings, $api))->sync((new \TypesenseSearch\PinnedResults\Repository($settings))->all());
            if (!$result['ok']) {
                throw new \TypesenseSearch\Multisite\SetupException(__('Pinned-result synchronization failed.', 'typesense-search'));
            }
        }
    }
}
