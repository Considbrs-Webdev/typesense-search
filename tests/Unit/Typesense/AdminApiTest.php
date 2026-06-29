<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Typesense;

use Brain\Monkey\Functions;
use Mockery;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Tests\TestCase;
use TypesenseSearch\Typesense\AdminApi;

class AdminApiTest extends TestCase
{
    // ── getServerVersion ──────────────────────────────────────────────────────

    public function test_get_server_version_returns_empty_string_when_remote_not_configured(): void
    {
        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('getRemote')->andReturn('');

        self::assertSame('', (new AdminApi($settings))->getServerVersion());
    }

    public function test_get_server_version_returns_empty_string_on_wp_error(): void
    {
        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('getRemote')->andReturn('https://search.example.com');
        $settings->shouldReceive('getAdminKey')->andReturn('secret');

        Functions\expect('trailingslashit')->andReturnUsing(fn(string $s): string => rtrim($s, '/') . '/');
        Functions\expect('wp_remote_get')->andReturn(Mockery::mock(\WP_Error::class, [
            'get_error_message' => 'connection refused',
        ]));
        Functions\expect('is_wp_error')->andReturn(true);

        self::assertSame('', (new AdminApi($settings))->getServerVersion());
    }

    public function test_get_server_version_returns_empty_string_on_non_2xx_status(): void
    {
        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('getRemote')->andReturn('https://search.example.com');
        $settings->shouldReceive('getAdminKey')->andReturn('');

        Functions\expect('trailingslashit')->andReturnUsing(fn(string $s): string => rtrim($s, '/') . '/');
        Functions\expect('wp_remote_get')->andReturn(['fake-response']);
        Functions\expect('is_wp_error')->andReturn(false);
        Functions\expect('wp_remote_retrieve_response_code')->andReturn(401);

        self::assertSame('', (new AdminApi($settings))->getServerVersion());
    }

    public function test_get_server_version_returns_normalised_version_on_success(): void
    {
        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('getRemote')->andReturn('https://search.example.com');
        $settings->shouldReceive('getAdminKey')->andReturn('secret');

        Functions\expect('trailingslashit')->andReturnUsing(fn(string $s): string => rtrim($s, '/') . '/');
        Functions\expect('wp_remote_get')->andReturn(['fake-response']);
        Functions\expect('is_wp_error')->andReturn(false);
        Functions\expect('wp_remote_retrieve_response_code')->andReturn(200);
        Functions\expect('wp_remote_retrieve_body')->andReturn('{"version":"v31.0.0"}');

        self::assertSame('31.0.0', (new AdminApi($settings))->getServerVersion());
    }

    // ── request ───────────────────────────────────────────────────────────────

    public function test_request_returns_ok_false_on_wp_error(): void
    {
        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('getAdminKey')->andReturn('secret');

        Functions\expect('wp_json_encode')->andReturn('{"items":[]}');
        Functions\expect('wp_remote_request')->andReturn(Mockery::mock(\WP_Error::class, [
            'get_error_message' => 'timeout',
        ]));
        Functions\expect('is_wp_error')->andReturn(true);

        $result = (new AdminApi($settings))->request('PUT', 'https://search.example.com/curation_sets/test', ['items' => []]);

        self::assertFalse($result['ok']);
        self::assertSame('timeout', $result['message']);
        self::assertSame('', $result['body']);
    }

    public function test_request_returns_ok_false_on_non_2xx_response(): void
    {
        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('getAdminKey')->andReturn('secret');

        Functions\expect('wp_remote_request')->andReturn(['fake-response']);
        Functions\expect('is_wp_error')->andReturn(false);
        Functions\expect('wp_remote_retrieve_response_code')->andReturn(404);
        Functions\expect('wp_remote_retrieve_body')->andReturn('Not found');

        $result = (new AdminApi($settings))->request('GET', 'https://search.example.com/collections/missing');

        self::assertFalse($result['ok']);
        self::assertStringContainsString('404', $result['message']);
    }

    public function test_request_returns_ok_true_on_2xx_response(): void
    {
        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('getAdminKey')->andReturn('secret');

        Functions\expect('wp_remote_request')->andReturn(['fake-response']);
        Functions\expect('is_wp_error')->andReturn(false);
        Functions\expect('wp_remote_retrieve_response_code')->andReturn(200);
        Functions\expect('wp_remote_retrieve_body')->andReturn('{"ok":true}');

        $result = (new AdminApi($settings))->request('GET', 'https://search.example.com/health');

        self::assertTrue($result['ok']);
        self::assertSame('{"ok":true}', $result['body']);
    }
}
