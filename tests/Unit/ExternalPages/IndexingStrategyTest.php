<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\ExternalPages;

use Brain\Monkey\Functions;
use Mockery;
use TypesenseSearch\ExternalPages\IndexingStrategy;
use TypesenseSearch\ExternalPages\Repository;
use TypesenseSearch\Logger\LoggerInterface;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Services\TypesenseClientService;
use TypesenseSearch\Tests\TestCase;

class IndexingStrategyTest extends TestCase
{
    private function strategy(): IndexingStrategy
    {
        return new IndexingStrategy(
            Mockery::mock(TypesenseClientService::class),
            Mockery::mock(SettingsRepository::class),
            Mockery::mock(LoggerInterface::class),
            Mockery::mock(Repository::class)
        );
    }

    private function build(IndexingStrategy $strategy, array $page): mixed
    {
        $method = new \ReflectionMethod($strategy, 'buildDocument');

        return $method->invoke($strategy, $page);
    }

    public function test_document_uses_namespaced_id_and_external_page_type(): void
    {
        Functions\when('wp_trim_words')->returnArg(1);

        $document = $this->build($this->strategy(), [
            'id'         => 42,
            'title'      => 'Book a room',
            'content'    => 'Reserve meeting rooms',
            'url'        => 'https://booking.example.com/rooms',
            'updated_at' => '2026-01-02 03:04:05',
        ]);

        self::assertSame('external-42', $document->get('id'));
        self::assertSame('external_page', $document->get('type'));
        self::assertSame('External page', $document->get('type_name'));
        self::assertSame('https://booking.example.com/rooms', $document->get('url'));
        self::assertNull($document->get('date'), 'External pages have no date, so hits must not show one.');
    }

    public function test_document_is_skipped_without_title_or_url(): void
    {
        $strategy = $this->strategy();

        self::assertFalse($this->build($strategy, ['id' => 1, 'title' => '', 'url' => 'https://example.com']));
        self::assertFalse($this->build($strategy, ['id' => 1, 'title' => 'X', 'url' => '']));
    }
}
