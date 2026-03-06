<?php

namespace TypesenseSearch\Indexing\Strategies;

use TypesenseSearch\Admin\Settings;
use TypesenseSearch\Indexing\Contracts\IndexingStrategyInterface;
use TypesenseSearch\Indexing\IndexableDocument;
use TypesenseSearch\Typesense\ClientFactory;

/**
 * Class AbstractIndexingStrategy
 *
 * Provides shared Typesense client interaction logic (upsert and delete) so
 * that concrete strategies only need to implement the content-specific parts:
 * supports(), shouldIndex(), buildDocument(), and registerHooks().
 *
 * Subclasses may override index() and deindex() for custom behaviour, but
 * the default implementations here cover the standard upsert/delete pattern.
 *
 * @package TypesenseSearch\Indexing\Strategies
 */
abstract class AbstractIndexingStrategy implements IndexingStrategyInterface
{
    /**
     * {@inheritdoc}
     *
     * Default implementation: builds the document and upserts it into
     * Typesense. Returns false when the client is unavailable, the
     * collection is not configured, or the document build fails.
     */
    public function index(\WP_Post $post): bool
    {
        $client         = $this->getClient();
        $collectionName = $this->getCollectionName();

        if ($client === null || $collectionName === '') {
            return false;
        }

        $document = $this->buildDocument($post);
        if ($document === false) {
            return false;
        }

        try {
            $client->collections[$collectionName]->documents->upsert($document->toArray());
            return true;
        } catch (\Exception $e) {
            error_log(sprintf(
                '[TypesenseSearch][%s] Failed to index post %d: %s',
                $this->getIdentifier(),
                $post->ID,
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * {@inheritdoc}
     *
     * Default implementation: deletes the document from Typesense by post ID.
     * Treats "not found" as success so callers need not check existence first.
     */
    public function deindex(int $postId): bool
    {
        $client         = $this->getClient();
        $collectionName = $this->getCollectionName();

        if ($client === null || $collectionName === '') {
            return false;
        }

        try {
            $client->collections[$collectionName]->documents[(string) $postId]->delete();
            return true;
        } catch (\Typesense\Exceptions\ObjectNotFound $e) {
            return true; // Already absent — treat as success.
        } catch (\Exception $e) {
            error_log(sprintf(
                '[TypesenseSearch][%s] Failed to deindex post %d: %s',
                $this->getIdentifier(),
                $postId,
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Default no-op hook registration. Override in subclasses that need
     * content-specific WordPress hooks beyond the standard post lifecycle.
     */
    public function registerHooks(): void
    {
        // No-op by default.
    }

    /**
     * Return the configured Typesense client, or null when credentials are
     * missing or invalid.
     *
     * Exposed as a protected helper so that subclasses that override index()
     * or deindex() can obtain the client without importing ClientFactory
     * directly.
     *
     * @return mixed
     */
    protected function getClient(): mixed
    {
        return ClientFactory::fromOptions();
    }

    /**
     * Retrieve the configured Typesense collection name.
     *
     * @return string
     */
    protected function getCollectionName(): string
    {
        return (string) get_option(Settings::OPTION_INDEX_NAME, '');
    }
}
