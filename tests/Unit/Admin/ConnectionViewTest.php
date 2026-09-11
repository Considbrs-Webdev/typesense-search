<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use TypesenseSearch\Admin\Settings;
use TypesenseSearch\Tests\TestCase;

/**
 * Renders the real connection.php view to prove the saved admin (indexing)
 * key never appears in the output — the concrete regression this class
 * guards against is esc_attr(get_option(OPTION_ADMIN_KEY)) creeping back
 * into the value="" attribute.
 */
class ConnectionViewTest extends TestCase
{
    private array $options = [];

    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('is_multisite')->justReturn(false);
        Functions\when('get_option')->alias(fn ($key, $default = false) => $this->options[$key] ?? $default);
        foreach (['esc_html', 'esc_attr', 'esc_url'] as $function) {
            Functions\when($function)->alias(static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES));
        }
        Functions\when('esc_html_e')->alias(static function ($value) { echo htmlspecialchars($value, ENT_QUOTES); });
        Functions\when('esc_attr_e')->alias(static function ($value) { echo htmlspecialchars($value, ENT_QUOTES); });
        Functions\when('esc_html__')->returnArg(1);
        Functions\when('esc_attr__')->returnArg(1);
        Functions\when('settings_fields')->justReturn('');
        Functions\when('submit_button')->justReturn('');
    }

    private function render(bool $provisioningAvailable = true): string
    {
        $activeTab = 'connection';
        ob_start();
        try {
            include dirname(__DIR__, 3) . '/views/admin/settings-tabs/connection.php';
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    public function test_saved_admin_key_never_appears_in_the_rendered_form(): void
    {
        $this->options[Settings::OPTION_ADMIN_KEY] = 'super-secret-admin-key';
        $html = $this->render();

        self::assertStringNotContainsString('super-secret-admin-key', $html);
        self::assertMatchesRegularExpression('/id="ts-admin-key"[^>]*value=""/', $html);
        self::assertStringContainsString('_clear" value="1"', $html);
    }

    public function test_saved_search_key_is_shown_because_it_is_meant_to_be_public(): void
    {
        $this->options[Settings::OPTION_SEARCH_KEY] = 'public-search-key';
        $html = $this->render();

        self::assertStringContainsString('public-search-key', $html);
    }

    public function test_generate_button_is_hidden_without_a_provisioning_key(): void
    {
        $html = $this->render(false);

        self::assertStringNotContainsString('id="ts-generate-search-key"', $html);
        self::assertStringContainsString('Automatic generation is unavailable', $html);
    }

    public function test_generate_button_is_shown_with_a_provisioning_key(): void
    {
        $html = $this->render(true);

        self::assertStringContainsString('id="ts-generate-search-key"', $html);
    }
}
