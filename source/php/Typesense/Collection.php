<?php

namespace TypesenseSearch\Typesense;

use Typesense\Client;
use Typesense\Exceptions\ObjectNotFound;

/**
 * Class Collection
 *
 * Manages the Typesense collection schema and lifecycle.
 *
 * The default schema includes a `.*` wildcard field (type "auto") so that
 * any extra fields sent during indexing are automatically added without
 * requiring a schema update. Plugin authors and site developers can override
 * or extend the schema via the `typesense_search_collection_schema` filter:
 *
 *   add_filter('typesense_search_collection_schema', function (array $schema): array {
 *       // Add a custom numeric field
 *       $schema['fields'][] = ['name' => 'rating', 'type' => 'float', 'optional' => true];
 *       // Or replace the schema entirely
 *       return $schema;
 *   });
 *
 * @package TypesenseSearch\Typesense
 */
class Collection
{
    /**
     * The WordPress filter hook name used to override the collection schema.
     */
    public const FILTER_SCHEMA = 'Municipio/TypesenseSearch/Collection/getSchema';

    /**
     * Returns the collection schema to use when creating the collection.
     *
     * The returned array is passed through the `Municipio/TypesenseSearch/Collection/getSchema`
     * filter, giving developers full control over the schema.
     *
     * The `.*` catch-all field (type "auto") ensures that any extra fields sent
     * during indexing are indexed automatically even if they were not declared
     * in the schema up-front.
     *
     * @param string $collectionName The Typesense collection name.
     * @return array<string, mixed>
     */
    public static function getSchema(string $collectionName): array
    {
        $schema = [
            'name'   => $collectionName,
            'fields' => [
                ['name' => 'id',        'type' => 'string'],
                ['name' => 'title',     'type' => 'string'],
                ['name' => 'content',   'type' => 'string'],
                ['name' => 'excerpt',   'type' => 'string',  'optional' => true],
                ['name' => 'url',       'type' => 'string',  'index'    => false],
                ['name' => 'post_type', 'type' => 'string',  'facet'    => true],
                ['name' => 'date',      'type' => 'int64',   'optional' => true],
                ['name' => 'thumbnail', 'type' => 'string',  'optional' => true, 'index' => false],
                // Catch-all: any extra field sent during indexing is auto-typed
                // and added to the schema on first use.
                ['name' => '.*',        'type' => 'auto'],
            ],
        ];

        /**
         * Filters the Typesense collection schema before the collection is created.
         *
         * @param array<string, mixed> $schema         The default schema array.
         * @param string               $collectionName The target collection name.
         */
        return (array) apply_filters(self::FILTER_SCHEMA, $schema, $collectionName);
    }

    /**
     * Creates the collection in Typesense using the resolved schema.
     *
     * @param Client $client         An authenticated Typesense client.
     * @param string $collectionName The name of the collection to create.
     * @throws \Exception On API failure.
     */
    public static function create(Client $client, string $collectionName): void
    {
        $client->collections->create(self::getSchema($collectionName));
    }

    /**
     * Returns true when the named collection already exists in Typesense.
     *
     * @param Client $client         An authenticated Typesense client.
     * @param string $collectionName The collection name to test.
     */
    public static function exists(Client $client, string $collectionName): bool
    {
        try {
            $client->collections[$collectionName]->retrieve();
            return true;
        } catch (ObjectNotFound $e) {
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
