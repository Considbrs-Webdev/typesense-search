<?php

namespace TypesenseSearch\Indexing\Strategies;

use TypesenseSearch\Indexing\Contracts\IndexingStrategyInterface;
use TypesenseSearch\Indexing\IndexableDocument;
use TypesenseSearch\Logger\LoggerInterface;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Services\TypesenseClientService;

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
 * ── Dependency injection ─────────────────────────────────────────────────
 *
 * The constructor requires three services. Pass them from the composition
 * root (App) or build them at the registration site:
 *
 *   $settings = new SettingsRepository();
 *   $client   = new TypesenseClientService($settings);
 *   $logger   = new ErrorLogLogger();
 *   $registry->register(new MyCustomStrategy($client, $settings, $logger));
 *
 * @package TypesenseSearch\Indexing\Strategies
 */
abstract class AbstractIndexingStrategy implements IndexingStrategyInterface
{
    private TypesenseClientService $clientService;
    private SettingsRepository $settings;
    protected LoggerInterface $logger;

    public function __construct(
        TypesenseClientService $clientService,
        SettingsRepository $settings,
        LoggerInterface $logger
    ) {
        $this->settings      = $settings;
        $this->clientService = $clientService;
        $this->logger        = $logger;
    }

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

        try {
            $document = $this->buildDocument($post);
            if ($document === false) {
                return false;
            }

            $client->collections[$collectionName]->documents->upsert($document->toArray());
            return true;
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                '[TypesenseSearch][%s] Document for post %d could not be indexed: %s',
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
            $this->logger->error(sprintf(
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
        return $this->clientService->getClient();
    }

    /**
     * Retrieve the configured Typesense collection name.
     *
     * @return string
     */
    protected function getCollectionName(): string
    {
        return $this->settings->getCollectionName();
    }

    /**
     * Provide subclasses access to the settings repository.
     *
     * Useful when a concrete strategy needs to read additional settings
     * (e.g. whether a feature is enabled) without importing get_option()
     * or Settings directly.
     */
    protected function getSettings(): SettingsRepository
    {
        return $this->settings;
    }
}
