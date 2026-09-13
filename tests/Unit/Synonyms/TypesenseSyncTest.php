<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Synonyms;

use Brain\Monkey\Functions;
use Mockery;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Synonyms\TypesenseSync;
use TypesenseSearch\Tests\TestCase;
use TypesenseSearch\Typesense\AdminApi;

class TypesenseSyncTest extends TestCase
{
    public function test_sync_adds_site_locale_to_synonyms(): void
    {
        Functions\when('get_locale')->justReturn('sv_SE');

        $synonymBody = $this->syncAndCaptureSynonymBody();

        self::assertSame('sv', $synonymBody['items'][0]['locale']);
    }

    public function test_sync_falls_back_to_english_locale_when_site_locale_is_unset(): void
    {
        Functions\when('get_locale')->justReturn('');

        $synonymBody = $this->syncAndCaptureSynonymBody();

        self::assertSame('en', $synonymBody['items'][0]['locale']);
    }

    /**
     * @return array<string, mixed>
     */
    private function syncAndCaptureSynonymBody(): array
    {
        Functions\when('_n')->alias(
            static fn (string $single, string $plural, int $number, string $domain): string =>
                $number === 1 ? $single : $plural
        );

        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('getRemote')->andReturn('https://search.example.com');
        $settings->shouldReceive('getAdminKey')->andReturn('secret');
        $settings->shouldReceive('getCollectionName')->andReturn('my_collection');

        $synonymBody = null;
        $adminApi = Mockery::mock(AdminApi::class);
        $adminApi->shouldReceive('request')
            ->once()
            ->with(
                'PUT',
                'https://search.example.com/synonym_sets/wordpress-synonyms-my_collection',
                Mockery::on(static function (array $body) use (&$synonymBody): bool {
                    $synonymBody = $body;
                    return true;
                })
            )
            ->andReturn(['ok' => true, 'message' => '', 'body' => '']);
        $adminApi->shouldReceive('request')
            ->once()
            ->with('GET', 'https://search.example.com/collections/my_collection')
            ->andReturn(['ok' => true, 'message' => '', 'body' => '{"synonym_sets":[]}']);
        $adminApi->shouldReceive('request')
            ->once()
            ->with(
                'PATCH',
                'https://search.example.com/collections/my_collection',
                ['synonym_sets' => ['wordpress-synonyms-my_collection']]
            )
            ->andReturn(['ok' => true, 'message' => '', 'body' => '']);

        $result = (new TypesenseSync($settings, $adminApi))->sync([
            ['id' => 7, 'terms' => ['lekplatser', 'playground']],
        ]);

        self::assertTrue($result['ok']);
        self::assertIsArray($synonymBody);

        return $synonymBody;
    }
}
