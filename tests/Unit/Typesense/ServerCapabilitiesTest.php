<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Typesense;

use Mockery;
use TypesenseSearch\Tests\TestCase;
use TypesenseSearch\Typesense\AdminApi;
use TypesenseSearch\Typesense\ServerCapabilities;

class ServerCapabilitiesTest extends TestCase
{
    // ── getServerVersion ──────────────────────────────────────────────────────

    public function test_get_server_version_delegates_to_admin_api(): void
    {
        $adminApi = Mockery::mock(AdminApi::class);
        $adminApi->shouldReceive('getServerVersion')->once()->andReturn('31.0.0');

        self::assertSame('31.0.0', (new ServerCapabilities($adminApi))->getServerVersion());
    }

    public function test_get_server_version_is_cached_per_instance(): void
    {
        $adminApi = Mockery::mock(AdminApi::class);
        $adminApi->shouldReceive('getServerVersion')->once()->andReturn('31.0.0');

        $capabilities = new ServerCapabilities($adminApi);

        self::assertSame('31.0.0', $capabilities->getServerVersion());
        self::assertSame('31.0.0', $capabilities->getServerVersion()); // cache hit — no second API call
    }

    public function test_cache_is_per_instance_not_shared(): void
    {
        $adminApi1 = Mockery::mock(AdminApi::class);
        $adminApi1->shouldReceive('getServerVersion')->once()->andReturn('30.0.0');

        $adminApi2 = Mockery::mock(AdminApi::class);
        $adminApi2->shouldReceive('getServerVersion')->once()->andReturn('31.0.0');

        self::assertSame('30.0.0', (new ServerCapabilities($adminApi1))->getServerVersion());
        self::assertSame('31.0.0', (new ServerCapabilities($adminApi2))->getServerVersion());
    }

    // ── supportsCurationSets ──────────────────────────────────────────────────

    public function test_supports_curation_sets_returns_true_for_version_at_minimum(): void
    {
        $adminApi = Mockery::mock(AdminApi::class);
        $adminApi->shouldReceive('getServerVersion')->andReturn('30.0.0');

        self::assertTrue((new ServerCapabilities($adminApi))->supportsCurationSets());
    }

    public function test_supports_curation_sets_returns_true_for_newer_version(): void
    {
        $adminApi = Mockery::mock(AdminApi::class);
        $adminApi->shouldReceive('getServerVersion')->andReturn('31.0.0');

        self::assertTrue((new ServerCapabilities($adminApi))->supportsCurationSets());
    }

    public function test_supports_curation_sets_returns_false_for_older_version(): void
    {
        $adminApi = Mockery::mock(AdminApi::class);
        $adminApi->shouldReceive('getServerVersion')->andReturn('29.9.9');

        self::assertFalse((new ServerCapabilities($adminApi))->supportsCurationSets());
    }

    public function test_supports_curation_sets_returns_false_when_version_is_empty(): void
    {
        $adminApi = Mockery::mock(AdminApi::class);
        $adminApi->shouldReceive('getServerVersion')->andReturn('');

        self::assertFalse((new ServerCapabilities($adminApi))->supportsCurationSets());
    }
}
