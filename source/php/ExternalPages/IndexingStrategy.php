<?php

namespace TypesenseSearch\ExternalPages;

use TypesenseSearch\Indexing\IndexableDocument;
use TypesenseSearch\Indexing\Strategies\AbstractExternalIndexingStrategy;
use TypesenseSearch\Logger\LoggerInterface;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Services\TypesenseClientService;

/**
 * Indexes editor-managed external pages into the shared search collection.
 *
 * Documents use an "external-<id>" id so they cannot collide with WordPress
 * post ids, and the 'external_page' type so they can be filtered and labelled.
 */
class IndexingStrategy extends AbstractExternalIndexingStrategy
{
    public const IDENTIFIER = 'external-pages';
    public const TYPE       = 'external_page';
    public const ID_PREFIX  = 'external-';

    private const EXCERPT_WORDS = 30;

    public function __construct(
        TypesenseClientService $clientService,
        SettingsRepository $settings,
        LoggerInterface $logger,
        private readonly Repository $repository
    ) {
        parent::__construct($clientService, $settings, $logger);
    }

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function syncAll(): int
    {
        return $this->sync()['indexed'];
    }

    /**
     * Upserts every stored page, removes documents whose page no longer
     * exists, and records the outcome on each row.
     *
     * @return array{ok: bool, message: string, indexed: int}
     */
    public function sync(): array
    {
        $client         = $this->getClient();
        $collectionName = $this->getCollectionName();

        if ($client === null || $collectionName === '') {
            return [
                'ok'      => false,
                'message' => __('Typesense connection settings are incomplete.', 'typesense-search'),
                'indexed' => 0,
            ];
        }

        $pages   = $this->repository->all();
        $indexed = 0;
        $failed  = 0;

        foreach ($pages as $page) {
            try {
                $document = $this->buildDocument($page);
                if ($document === false) {
                    throw new \RuntimeException('Document could not be built.');
                }

                $client->collections[$collectionName]->documents->upsert($document->toArray());
                $this->repository->markSynced((int) $page['id']);
                $indexed++;
            } catch (\Throwable $e) {
                $failed++;
                $this->repository->markSyncError((int) $page['id'], $e->getMessage());
                $this->logger->error(sprintf(
                    '[TypesenseSearch][%s] Document "%s" could not be indexed: %s',
                    $this->getIdentifier(),
                    $this->getExternalId($page),
                    $e->getMessage()
                ));
            }
        }

        $pruneError = $this->pruneRemoved($client, $collectionName, $pages);
        if ($pruneError !== null) {
            return ['ok' => false, 'message' => $pruneError, 'indexed' => $indexed];
        }

        if ($failed > 0) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    /* translators: %d: number of external pages that failed to sync */
                    _n('%d external page could not be synced.', '%d external pages could not be synced.', $failed, 'typesense-search'),
                    $failed
                ),
                'indexed' => $indexed,
            ];
        }

        return [
            'ok'      => true,
            'message' => sprintf(
                /* translators: %d: number of external pages */
                _n('Synced %d external page.', 'Synced %d external pages.', $indexed, 'typesense-search'),
                $indexed
            ),
            'indexed' => $indexed,
        ];
    }

    protected function fetchItems(): iterable
    {
        return $this->repository->all();
    }

    protected function buildDocument(mixed $item): IndexableDocument|false
    {
        $title = (string) ($item['title'] ?? '');
        $url   = (string) ($item['url'] ?? '');

        if ($title === '' || $url === '') {
            return false;
        }

        $content   = (string) ($item['content'] ?? '');
        $updatedAt = strtotime((string) ($item['updated_at'] ?? '') . ' UTC');

        return new IndexableDocument([
            'id'        => $this->getExternalId($item),
            'title'     => $title,
            'content'   => $content,
            'excerpt'   => wp_trim_words($content, self::EXCERPT_WORDS),
            'url'       => $url,
            'type'      => self::TYPE,
            'type_name' => __('External page', 'typesense-search'),
            'date'      => $updatedAt !== false ? $updatedAt : 0,
        ]);
    }

    protected function getExternalId(mixed $item): string
    {
        return self::ID_PREFIX . (int) ($item['id'] ?? 0);
    }

    /**
     * Deletes indexed external pages that no longer exist in the database.
     *
     * @param array<int, array<string, mixed>> $pages
     * @return string|null Error message, or null on success.
     */
    private function pruneRemoved(mixed $client, string $collectionName, array $pages): ?string
    {
        $filter = 'type:=' . self::TYPE;

        if ($pages !== []) {
            $ids     = array_map(fn (array $page): string => '`' . $this->getExternalId($page) . '`', $pages);
            $filter .= ' && id:!=[' . implode(',', $ids) . ']';
        }

        try {
            $client->collections[$collectionName]->documents->delete(['filter_by' => $filter]);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                '[TypesenseSearch][%s] Failed to remove deleted external pages from the index: %s',
                $this->getIdentifier(),
                $e->getMessage()
            ));

            return $e->getMessage();
        }

        return null;
    }
}
