<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Frontend;

use Brain\Monkey\Functions;
use Mockery;
use TypesenseSearch\Frontend\EnrichSearchTemplate;
use TypesenseSearch\Frontend\PlainSearchTemplate;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Tests\TestCase;

class PlainSearchTemplateTest extends TestCase
{
    public function test_keeps_theme_template_outside_search_pages(): void
    {
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_search')->justReturn(false);

        $settings = Mockery::mock(SettingsRepository::class);
        $plain    = new PlainSearchTemplate($settings, new EnrichSearchTemplate($settings));

        self::assertSame('/theme/index.php', $plain->maybeUsePlainTemplate('/theme/index.php'));
    }

    public function test_keeps_theme_template_when_typesense_is_unavailable(): void
    {
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_search')->justReturn(true);

        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('canUseTypesense')->andReturn(false);
        $plain = new PlainSearchTemplate($settings, new EnrichSearchTemplate($settings));

        self::assertSame('/theme/search.php', $plain->maybeUsePlainTemplate('/theme/search.php'));
    }
}
