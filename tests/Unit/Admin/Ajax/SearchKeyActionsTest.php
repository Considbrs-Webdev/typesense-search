<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Admin\Ajax;

use Brain\Monkey\Functions;
use TypesenseSearch\Admin\Ajax\SearchKeyActions;
use TypesenseSearch\Admin\Settings;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Tests\TestCase;

/**
 * These tests never configure a provisioning key/constant, so every path that
 * reaches ProvisioningClientFactory hits the real "unavailable" guard — no
 * network call or static mocking needed. That guard, and never leaking a raw
 * SDK message, are exactly what changed in this class.
 */
class SearchKeyActionsTest extends TestCase
{
    private array $options = [];

    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('is_multisite')->justReturn(false);
        Functions\when('wp_unslash')->returnArg(1);
        Functions\when('get_option')->alias(fn ($key, $default = false) => $this->options[$key] ?? $default);
        Functions\when('update_option')->alias(function ($key, $value) { $this->options[$key] = $value; return true; });
    }

    protected function tearDown(): void
    {
        $_POST = [];
        parent::tearDown();
    }

    private function subject(): SearchKeyActions
    {
        return new SearchKeyActions(new SettingsRepository());
    }

    public function test_generate_requires_a_collection_name(): void
    {
        $_POST = [];
        $error = null;
        Functions\when('wp_send_json_error')->alias(function ($data) use (&$error) { $error = $data['message']; });

        $this->subject()->handleGenerateSearchKey();

        self::assertStringContainsString('collection name', $error);
    }

    public function test_generate_refuses_when_no_remote_is_saved_and_suggests_the_manual_field(): void
    {
        $_POST = ['collection_name' => 'my-site'];
        $error = null;
        Functions\when('wp_send_json_error')->alias(function ($data) use (&$error) { $error = $data['message']; });

        $this->subject()->handleGenerateSearchKey();

        self::assertStringContainsString('manually', $error);
    }

    public function test_generate_never_reads_remote_or_admin_key_from_post(): void
    {
        // A form-supplied remote/admin_key must never steer where the
        // provisioning key is sent — the handler must ignore these entirely.
        $_POST = [
            'collection_name' => 'my-site',
            'remote'          => 'https://attacker.example.com',
            'admin_key'       => 'whatever',
        ];
        $this->options[Settings::OPTION_REMOTE] = '';
        $error = null;
        Functions\when('wp_send_json_error')->alias(function ($data) use (&$error) { $error = $data['message']; });

        $this->subject()->handleGenerateSearchKey();

        // Falls back to "no saved remote", proving the POSTed remote was ignored.
        self::assertStringContainsString('manually', $error);
    }

    public function test_generate_reports_unavailable_provisioning_key_without_a_raw_sdk_message(): void
    {
        $_POST = ['collection_name' => 'my-site'];
        $this->options[Settings::OPTION_REMOTE] = 'https://search.example.com';
        $error = null;
        Functions\when('wp_send_json_error')->alias(function ($data) use (&$error) { $error = $data['message']; });

        $this->subject()->handleGenerateSearchKey();

        self::assertStringContainsString('No provisioning key is configured', $error);
        self::assertStringNotContainsString('keys:create', $error);
    }

    public function test_fix_requires_a_saved_remote_and_collection(): void
    {
        $error = null;
        Functions\when('wp_send_json_error')->alias(function ($data) use (&$error) { $error = $data['message']; });

        $this->subject()->handleFixSearchKey();

        self::assertStringContainsString('must both be saved', $error);
    }

    public function test_fix_reports_unavailable_provisioning_key_and_does_not_touch_the_saved_search_key(): void
    {
        $this->options[Settings::OPTION_REMOTE] = 'https://search.example.com';
        $this->options[Settings::OPTION_INDEX_NAME] = 'my-site';
        $this->options[Settings::OPTION_SEARCH_KEY] = 'still-here';
        $error = null;
        Functions\when('wp_send_json_error')->alias(function ($data) use (&$error) { $error = $data['message']; });

        $this->subject()->handleFixSearchKey();

        self::assertStringContainsString('No provisioning key is configured', $error);
        self::assertSame('still-here', $this->options[Settings::OPTION_SEARCH_KEY]);
    }

    public function test_generate_is_gated_by_permission_check(): void
    {
        // wp_send_json_error() calls wp_die() in real WordPress and never
        // returns; simulate that here so execution stops at the first call,
        // the same way requirePermission() behaves in production.
        Functions\when('current_user_can')->justReturn(false);
        $status = null;
        Functions\when('wp_send_json_error')->alias(function ($data, $code = 200) use (&$status): void {
            $status = $code;
            throw new \RuntimeException('halted');
        });

        try {
            $this->subject()->handleGenerateSearchKey();
        } catch (\RuntimeException $e) {
            self::assertSame('halted', $e->getMessage());
        }

        self::assertSame(403, $status);
    }
}
