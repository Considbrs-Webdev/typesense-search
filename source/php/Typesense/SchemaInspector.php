<?php

namespace TypesenseSearch\Typesense;

use Typesense\Client;
use Typesense\Exceptions\ObjectNotFound;

/**
 * Inspects live Typesense collection schemas and persists verified schema state
 * so runtime feature readiness never relies on un-applied admin toggles.
 */
class SchemaInspector
{
    public const OPTION_SCHEMA_STATE = 'typesense_search_schema_state';

    private const SEARCHABLE_TEXT_FIELDS = ['title', 'content', 'excerpt', 'extra_terms'];

    /**
     * Extract normalized feature metadata from a Typesense collection schema array.
     *
     * @param array<string, mixed> $schema
     * @return array{
     *     collection: string,
     *     has_embedding: bool,
     *     embedding_model: string,
     *     embedding_from: string[],
     *     has_stemming: bool,
     *     locale: string
     * }
     */
    public static function analyzeSchema(array $schema): array
    {
        $hasEmbedding   = false;
        $embeddingModel = '';
        $embeddingFrom  = [];
        $stemmedFields  = 0;
        $textFieldCount = 0;
        $locale         = '';

        foreach ((array) ($schema['fields'] ?? []) as $field) {
            if (!is_array($field) || empty($field['name'])) {
                continue;
            }
            $name = (string) $field['name'];

            if ($name === 'embedding' && ($field['type'] ?? '') === 'float[]') {
                $modelName = trim((string) ($field['embed']['model_config']['model_name'] ?? ''));
                if ($modelName !== '') {
                    $hasEmbedding   = true;
                    $embeddingModel = $modelName;
                    $embeddingFrom  = array_values(array_map('strval', (array) ($field['embed']['from'] ?? [])));
                }
            }

            if (in_array($name, self::SEARCHABLE_TEXT_FIELDS, true)) {
                $textFieldCount++;
                if (!empty($field['stem'])) {
                    $stemmedFields++;
                }
                if ($name === 'title' && !empty($field['locale'])) {
                    $locale = (string) $field['locale'];
                }
            }
        }

        return [
            'collection'      => (string) ($schema['name'] ?? ''),
            'has_embedding'   => $hasEmbedding,
            'embedding_model' => $embeddingModel,
            'embedding_from'  => $embeddingFrom,
            'has_stemming'    => $textFieldCount > 0 && $stemmedFields === $textFieldCount,
            'locale'          => $locale,
        ];
    }

    /**
     * Retrieve and analyze the live collection schema from Typesense.
     * Returns null when the collection does not exist.
     *
     * @return array{
     *     collection: string,
     *     has_embedding: bool,
     *     embedding_model: string,
     *     embedding_from: string[],
     *     has_stemming: bool,
     *     locale: string
     * }|null
     */
    public static function inspectLive(Client $client, string $collectionName): ?array
    {
        if ($collectionName === '') {
            return null;
        }

        try {
            $schema = $client->collections[$collectionName]->retrieve();
            return self::analyzeSchema((array) $schema);
        } catch (ObjectNotFound $e) {
            return null;
        }
    }

    /**
     * Persist verified schema state for the current site (or target blog in multisite).
     *
     * @param array<string, mixed> $schema
     */
    public static function recordFromSchema(string $remote, array $schema, ?int $blogId = null): array
    {
        $analysis = self::analyzeSchema($schema);
        $state    = [
            'remote'          => rtrim($remote, '/'),
            'collection'      => $analysis['collection'],
            'has_embedding'   => $analysis['has_embedding'],
            'embedding_model' => $analysis['embedding_model'],
            'has_stemming'    => $analysis['has_stemming'],
            'verified_at'     => time(),
        ];

        self::writeOption($state, $blogId);

        return $state;
    }

    /**
     * Query Typesense for the live collection schema and persist the verified state.
     * Clears any saved state when the collection does not exist.
     *
     * @return array<string, mixed>|null Persisted state, or null when the collection is missing.
     */
    public static function refreshLiveState(
        Client $client,
        string $remote,
        string $collectionName,
        ?int $blogId = null
    ): ?array {
        if ($collectionName === '' || $remote === '') {
            return null;
        }

        try {
            $schema = (array) $client->collections[$collectionName]->retrieve();
            return self::recordFromSchema($remote, $schema, $blogId);
        } catch (ObjectNotFound $e) {
            self::clearState($blogId);
            return null;
        }
    }

    /**
     * Read persisted schema state only when it matches the given remote + collection.
     *
     * @return array{
     *     remote: string,
     *     collection: string,
     *     has_embedding: bool,
     *     embedding_model: string,
     *     has_stemming: bool,
     *     verified_at: int
     * }|null
     */
    public static function getVerifiedState(string $remote, string $collectionName, ?int $blogId = null): ?array
    {
        if ($remote === '' || $collectionName === '') {
            return null;
        }

        $raw = self::readOption($blogId);
        if (!is_array($raw)) {
            return null;
        }

        if (($raw['remote'] ?? '') !== rtrim($remote, '/') || ($raw['collection'] ?? '') !== $collectionName) {
            return null;
        }

        return [
            'remote'          => (string) $raw['remote'],
            'collection'      => (string) $raw['collection'],
            'has_embedding'   => !empty($raw['has_embedding']),
            'embedding_model' => (string) ($raw['embedding_model'] ?? ''),
            'has_stemming'    => !empty($raw['has_stemming']),
            'verified_at'     => (int) ($raw['verified_at'] ?? 0),
        ];
    }

    public static function clearState(?int $blogId = null): void
    {
        if ($blogId !== null && is_multisite()) {
            delete_blog_option($blogId, self::OPTION_SCHEMA_STATE);
            return;
        }

        delete_option(self::OPTION_SCHEMA_STATE);
    }

    /**
     * True only when verified live state confirms the collection has an `embedding`
     * field configured with `$expectedModel`.
     */
    public static function isEmbeddingReady(
        string $remote,
        string $collectionName,
        string $expectedModel,
        ?int $blogId = null
    ): bool {
        if ($expectedModel === '') {
            return false;
        }

        $state = self::getVerifiedState($remote, $collectionName, $blogId);
        if ($state === null) {
            return false;
        }

        return $state['has_embedding'] && $state['embedding_model'] === $expectedModel;
    }

    /**
     * Compare desired embedding/stemming configuration against verified schema state.
     *
     * @param array<string, mixed>|null $verifiedState
     * @return array{
     *     ok: bool,
     *     status: 'ok'|'unverified'|'rebuild_required'|'stale_embedding',
     *     reasons: string[]
     * }
     */
    public static function evaluateCompatibility(
        bool $vectorEnabled,
        string $expectedModel,
        bool $stemmingEnabled,
        ?array $verifiedState
    ): array {
        if ($verifiedState === null) {
            if ($vectorEnabled) {
                return [
                    'ok'      => false,
                    'status'  => 'unverified',
                    'reasons' => [
                        __(
                            'Embedded search is enabled, but the collection schema has not been verified yet. Run a status check or rebuild the collection (`wp typesense rebuild`). Keyword search remains active until verified.',
                            'typesense-search'
                        ),
                    ],
                ];
            }

            return ['ok' => true, 'status' => 'ok', 'reasons' => []];
        }

        $reasons = [];

        if ($vectorEnabled) {
            if (empty($verifiedState['has_embedding'])) {
                $reasons[] = __(
                    'Embedded search is enabled, but the live collection does not have an `embedding` field yet. Run `wp typesense rebuild` to recreate and re-index the collection. Keyword search remains active until rebuild completes.',
                    'typesense-search'
                );
            } elseif (($verifiedState['embedding_model'] ?? '') !== $expectedModel) {
                $reasons[] = sprintf(
                    /* translators: 1: active model on collection, 2: configured model */
                    __(
                        'Collection embedding model mismatch: index uses "%1$s", but "%2$s" is configured. Run `wp typesense rebuild` to apply the new model. Keyword search remains active until rebuild completes.',
                        'typesense-search'
                    ),
                    (string) ($verifiedState['embedding_model'] ?? ''),
                    $expectedModel
                );
            }
        }

        if ($stemmingEnabled !== !empty($verifiedState['has_stemming'])) {
            $reasons[] = $stemmingEnabled
                ? __(
                    'Stemming is enabled in settings, but searchable fields in the live collection were created without `stem: true`. Run `wp typesense rebuild` to apply stemming.',
                    'typesense-search'
                )
                : __(
                    'Stemming is disabled in settings, but searchable fields in the live collection still use `stem: true`. Run `wp typesense rebuild` to remove stemming.',
                    'typesense-search'
                );
        }

        if ($reasons !== []) {
            return [
                'ok'      => false,
                'status'  => 'rebuild_required',
                'reasons' => $reasons,
            ];
        }

        if (!$vectorEnabled && !empty($verifiedState['has_embedding'])) {
            return [
                'ok'      => true,
                'status'  => 'stale_embedding',
                'reasons' => [
                    sprintf(
                        /* translators: %s: model name */
                        __(
                            'Embedded search is disabled, but the collection still stores embeddings (%s). Search works normally; run `wp typesense rebuild` if you want to remove the unused embedding field.',
                            'typesense-search'
                        ),
                        (string) ($verifiedState['embedding_model'] ?? '')
                    ),
                ],
            ];
        }

        return ['ok' => true, 'status' => 'ok', 'reasons' => []];
    }

    private static function readOption(?int $blogId): mixed
    {
        if ($blogId !== null && is_multisite()) {
            return get_blog_option($blogId, self::OPTION_SCHEMA_STATE, null);
        }

        return get_option(self::OPTION_SCHEMA_STATE, null);
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function writeOption(array $state, ?int $blogId): void
    {
        if ($blogId !== null && is_multisite()) {
            update_blog_option($blogId, self::OPTION_SCHEMA_STATE, $state);
            return;
        }

        update_option(self::OPTION_SCHEMA_STATE, $state, false);
    }
}
