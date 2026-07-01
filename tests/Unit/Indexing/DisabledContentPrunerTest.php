<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Indexing;

use Mockery;
use Typesense\Client;
use Typesense\Collection;
use Typesense\Collections;
use Typesense\Documents;
use TypesenseSearch\Indexing\DisabledContentPruner;
use TypesenseSearch\Logger\LoggerInterface;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Services\TypesenseClientService;
use TypesenseSearch\Tests\TestCase;

class DisabledContentPrunerTest extends TestCase
{
    public function test_post_type_update_deletes_only_removed_types(): void
    {
        $deletedFilters = [];
        $pruner = $this->makePruner($deletedFilters);

        $pruner->onPostTypesUpdated(['post', 'page', 'event'], ['post']);

        self::assertSame(['type:=page', 'type:=event'], $deletedFilters);
    }

    public function test_post_type_update_does_not_delete_when_no_type_was_removed(): void
    {
        $deletedFilters = [];
        $clientService = Mockery::mock(TypesenseClientService::class);
        $clientService->shouldReceive('getClient')->never();

        $pruner = new DisabledContentPruner(
            $clientService,
            Mockery::mock(SettingsRepository::class),
            Mockery::mock(LoggerInterface::class)
        );

        $pruner->onPostTypesUpdated(['post'], ['post', 'page']);

        self::assertSame([], $deletedFilters);
    }

    public function test_pdf_update_deletes_attachments_when_pdf_indexing_is_disabled(): void
    {
        $deletedFilters = [];
        $pruner = $this->makePruner($deletedFilters);

        $pruner->onPdfIndexingUpdated(1, 0);

        self::assertSame(['type:=attachment'], $deletedFilters);
    }

    public function test_pdf_update_does_not_delete_when_pdf_indexing_stays_disabled(): void
    {
        $clientService = Mockery::mock(TypesenseClientService::class);
        $clientService->shouldReceive('getClient')->never();

        $pruner = new DisabledContentPruner(
            $clientService,
            Mockery::mock(SettingsRepository::class),
            Mockery::mock(LoggerInterface::class)
        );

        $pruner->onPdfIndexingUpdated(0, 0);

        self::assertTrue(true);
    }

    public function test_delete_is_skipped_when_typesense_is_not_configured(): void
    {
        $deletedFilters = [];
        $clientService = Mockery::mock(TypesenseClientService::class);
        $clientService->shouldReceive('getClient')->once()->andReturn(null);

        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('getCollectionName')->once()->andReturn('search');

        $pruner = new DisabledContentPruner(
            $clientService,
            $settings,
            Mockery::mock(LoggerInterface::class)
        );

        $pruner->onPdfIndexingUpdated(1, 0);

        self::assertSame([], $deletedFilters);
    }

    /**
     * @param string[] $deletedFilters
     */
    private function makePruner(array &$deletedFilters): DisabledContentPruner
    {
        $clientService = Mockery::mock(TypesenseClientService::class);
        $clientService->shouldReceive('getClient')->andReturn($this->makeClient($deletedFilters));

        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('getCollectionName')->andReturn('search');

        return new DisabledContentPruner(
            $clientService,
            $settings,
            Mockery::mock(LoggerInterface::class)
        );
    }

    /**
     * @param string[] $deletedFilters
     */
    private function makeClient(array &$deletedFilters): Client
    {
        $documents = new class ($deletedFilters) extends Documents {
            /** @var string[] */
            private array $deletedFilters;

            /**
             * @param string[] $deletedFilters
             */
            public function __construct(array &$deletedFilters)
            {
                $this->deletedFilters = &$deletedFilters;
            }

            public function delete(array $queryParams = []): array
            {
                $this->deletedFilters[] = (string) ($queryParams['filter_by'] ?? '');

                return ['num_deleted' => 1];
            }
        };

        $collection = new class ($documents) extends Collection {
            public function __construct(Documents $documents)
            {
                $this->documents = $documents;
            }
        };

        $collections = new class ($collection) extends Collections {
            public function __construct(private readonly Collection $collection)
            {
            }

            public function offsetGet(mixed $offset): Collection
            {
                return $this->collection;
            }
        };

        /** @var Client $client */
        $client = (new \ReflectionClass(Client::class))->newInstanceWithoutConstructor();
        $client->collections = $collections;

        return $client;
    }
}
