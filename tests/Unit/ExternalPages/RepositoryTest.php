<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\ExternalPages;

use Brain\Monkey\Functions;
use Mockery;
use TypesenseSearch\ExternalPages\Repository;
use TypesenseSearch\Tests\TestCase;

class RepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('sanitize_textarea_field')->alias(static fn (mixed $value): string => trim(strip_tags((string) $value)));
        Functions\when('esc_url_raw')->alias(static fn (string $url): string => $url);
    }

    public function test_save_rejects_empty_title(): void
    {
        $result = (new Repository())->save(null, '   ', 'text', 'https://example.com');

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('missing_title', $result->code);
    }

    /**
     * @dataProvider invalidUrls
     */
    public function test_save_rejects_non_http_urls(string $url): void
    {
        $result = (new Repository())->save(null, 'Booking', 'text', $url);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('invalid_url', $result->code);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUrls(): array
    {
        return [
            'empty'      => [''],
            'javascript' => ['javascript:alert(1)'],
            'relative'   => ['/boka'],
            'ftp'        => ['ftp://example.com/file'],
            'no host'    => ['https://'],
            'whitespace' => ['https:// example.com'],
        ];
    }

    public function test_delete_removes_row_by_id(): void
    {
        global $wpdb;

        $wpdb = Mockery::mock();
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('delete')
            ->once()
            ->with('wp_typesense_external_pages', ['id' => 7], ['%d'])
            ->andReturn(1);

        self::assertTrue((new Repository())->delete(7));
    }
}
