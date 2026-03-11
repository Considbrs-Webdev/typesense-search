<?php

namespace TypesenseSearch\Indexing\Strategies;

use TypesenseSearch\Indexing\Contracts\ExternalIndexingStrategyInterface;
use TypesenseSearch\Indexing\IndexableDocument;
use TypesenseSearch\Logger\LoggerInterface;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Services\TypesenseClientService;

/**
 * Class AbstractExternalIndexingStrategy
 *
 * Base class for all external indexing strategies. Handles the shared
 * Typesense interaction (upsert in syncAll, delete in deindex) so that
 * concrete subclasses only need to implement the source-specific parts:
 *
 *   getIdentifier() — unique slug for this strategy
 *   fetchItems()    — return every raw item from the external source
 *   buildDocument() — convert one raw item into an IndexableDocument
 *   getExternalId() — return the document 'id' that was set for one raw item
 *
 * ── Implementing a concrete strategy ─────────────────────────────────────
 *
 *   class MyFeedStrategy extends AbstractExternalIndexingStrategy
 *   {
 *       public function getIdentifier(): string { return 'my-feed'; }
 *
 *       protected function fetchItems(): iterable
 *       {
 *           // fetch from API, database, file, etc.
 *           return json_decode(file_get_contents('https://...'), true)['items'];
 *       }
 *
 *       protected function buildDocument(mixed $item): IndexableDocument|false
 *       {
 *           return new IndexableDocument([
 *               'id'    => 'my-feed-' . $item['id'],   // namespaced!
 *               'title' => $item['name'],
 *               // ...other fields
 *           ]);
 *       }
 *
 *       protected function getExternalId(mixed $item): string
 *       {
 *           return 'my-feed-' . $item['id'];
 *       }
 *   }
 *
 * See EServiceIndexingStrategy for a complete worked example including cron
 * registration.
 *
 * @package TypesenseSearch\Indexing\Strategies
 */
abstract class AbstractExternalIndexingStrategy implements ExternalIndexingStrategyInterface
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
     * Fetch all raw items from the external source.
     *
     * May return any iterable — array, Generator, or other Traversable. Each
     * element is passed as-is to buildDocument() and getExternalId(), so the
     * shape is entirely up to the concrete strategy.
     *
     * @return iterable
     */
    abstract protected function fetchItems(): iterable;

    /**
     * Build an IndexableDocument from one raw item.
     *
     * Return false when the item cannot be built (e.g. missing required
     * fields). That item will be skipped and counted as a failure in syncAll().
     *
     * The document's 'id' field MUST be a namespaced string to avoid
     * collisions with WordPress post IDs — see ExternalIndexingStrategyInterface
     * class docblock for the convention.
     *
     * @param mixed $item A single raw item returned by fetchItems().
     * @return IndexableDocument|false
     */
    abstract protected function buildDocument(mixed $item): IndexableDocument|false;

    /**
     * Return the namespaced Typesense document ID for a raw item.
     *
     * Must return the same value that was used as 'id' in buildDocument() so
     * that deindex() can remove the correct document.
     *
     * @param mixed $item A single raw item returned by fetchItems().
     * @return string
     */
    abstract protected function getExternalId(mixed $item): string;

    /**
     * {@inheritdoc}
     *
     * Iterates fetchItems(), builds each document, and upserts it. Failures
     * on individual items are logged and skipped so the rest of the batch
     * completes regardless.
     */
    public function syncAll(): int
    {
        $client         = $this->getClient();
        $collectionName = $this->getCollectionName();

        if ($client === null || $collectionName === '') {
            return 0;
        }

        $indexed = 0;

        foreach ($this->fetchItems() as $item) {
            $document = $this->buildDocument($item);

            if ($document === false) {
                $this->logger->warning(sprintf(
                    '[TypesenseSearch][%s] buildDocument() returned false, skipping item.',
                    $this->getIdentifier()
                ));
                continue;
            }

            try {
                $client->collections[$collectionName]->documents->upsert($document->toArray());
                $indexed++;
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    '[TypesenseSearch][%s] Failed to index document "%s": %s',
                    $this->getIdentifier(),
                    $document->get('id'),
                    $e->getMessage()
                ));
            }
        }

        return $indexed;
    }

    /**
     * {@inheritdoc}
     *
     * Deletes the document identified by $externalId from Typesense. Treats
     * "document not found" as success so callers need not check beforehand.
     */
    public function deindex(string $externalId): bool
    {
        $client         = $this->getClient();
        $collectionName = $this->getCollectionName();

        if ($client === null || $collectionName === '') {
            return false;
        }

        try {
            $client->collections[$collectionName]->documents[$externalId]->delete();
            return true;
        } catch (\Typesense\Exceptions\ObjectNotFound $e) {
            return true; // Already absent — treat as success.
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[TypesenseSearch][%s] Failed to deindex document "%s": %s',
                $this->getIdentifier(),
                $externalId,
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Default no-op hook registration. Override to schedule cron events or
     * register any other WordPress triggers for your sync.
     */
    public function registerHooks(): void
    {
        // No-op by default.
    }

    /**
     * Return the configured Typesense client, or null when credentials are
     * missing or invalid.
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
     */
    protected function getSettings(): SettingsRepository
    {
        return $this->settings;
    }
}
