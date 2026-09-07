<?php

namespace TypesenseSearch\Multisite;

use Typesense\Exceptions\ObjectNotFound;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Typesense\{AdminApi, ApiKey, ClientFactory, Collection, ServerCapabilities};

/** Typesense operations used by provisioning; replaceable in failure-path tests. */
class ProvisioningGateway
{
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

    public function key(array $connection, string $name): string
    {
        return ApiKey::generateSearchKey(ClientFactory::build($connection['remote'], $connection['admin_key']), $name);
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
                throw new \RuntimeException('Synonym synchronization failed.');
            }
        }
        if ($settings->isPinnedResultsEnabled()) {
            $result = (new \TypesenseSearch\PinnedResults\TypesenseSync($settings, $api))->sync((new \TypesenseSearch\PinnedResults\Repository($settings))->all());
            if (!$result['ok']) {
                throw new \RuntimeException('Pinned-result synchronization failed.');
            }
        }
    }
}
