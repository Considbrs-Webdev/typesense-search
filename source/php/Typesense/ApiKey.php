<?php

namespace TypesenseSearch\Typesense;

use Typesense\Client;

/**
 * Class ApiKey
 *
 * Manages scoped Typesense API keys.
 *
 * @package TypesenseSearch\Typesense
 */
class ApiKey
{
    /**
     * Generate a scoped search-only API key for the given collection and
     * return its value string.
     *
     * The key is granted the `documents:search` action only, making it safe
     * to embed in front-end JavaScript.
     *
     * @param Client $client         An authenticated admin Typesense client.
     * @param string $collectionName The collection the key should be scoped to.
     * @return string The raw key value.
     * @throws \RuntimeException When the API does not return a key value.
     * @throws \Exception        On any other Typesense API failure.
     */
    public static function generateSearchKey(Client $client, string $collectionName): string
    {
        $result = $client->keys->create([
            'description' => 'Search-only key for collection: ' . $collectionName,
            'actions'     => ['documents:search'],
            'collections' => [$collectionName],
        ]);

        if (empty($result['value'])) {
            throw new \RuntimeException(
                __('Key was created but no value was returned. Please check your Typesense dashboard.', 'typesense-search')
            );
        }

        return (string) $result['value'];
    }
}
