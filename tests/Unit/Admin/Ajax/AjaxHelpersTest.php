<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Admin\Ajax;

use Brain\Monkey\Functions;
use TypesenseSearch\Admin\Ajax\AjaxHelpers;
use TypesenseSearch\Tests\TestCase;

/**
 * Characterization tests for the AjaxHelpers trait.
 *
 * We instantiate an anonymous class that uses the trait and exposes the
 * private helpers as public wrappers so they can be called directly.
 *
 * Brain\Monkey's Functions\when() and Functions\expect() conflict when used
 * for the same function in the same test — so each test sets up its own stubs
 * rather than relying on setUp() for the functions under assertion.
 */
class AjaxHelpersTest extends TestCase
{
    /** @var object Anonymous test-double that exposes AjaxHelpers via public wrappers */
    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the subject once. Brain\Monkey stubs are global and apply
        // whenever the wrapped WP functions are called, so it is safe to create
        // the object before any individual test stubs are set up.
        $this->subject = new class {
            use AjaxHelpers;

            public function callRequirePermission(string $nonce): void
            {
                $this->requirePermission($nonce);
            }

            /** @return array{remote: string, adminKey: string} */
            public function callRequireConnectionFields(string $nonce): array
            {
                return $this->requireConnectionFields($nonce);
            }
        };
    }

    protected function tearDown(): void
    {
        $_POST = [];
        parent::tearDown();
    }

    // ─── requirePermission ────────────────────────────────────────────────────

    public function test_requirePermission_does_not_call_wp_send_json_error_when_authorized(): void
    {
        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\expect('wp_send_json_error')->never();

        $this->subject->callRequirePermission('my_nonce');

        // If we reach this line without an exception the guard passed.
        $this->addToAssertionCount(1);
    }

    public function test_requirePermission_calls_wp_send_json_error_with_403_when_unauthorized(): void
    {
        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(false);

        $called   = false;
        $calledStatus = 0;

        Functions\when('wp_send_json_error')->alias(
            static function (array $data, int $status = 200) use (&$called, &$calledStatus): void {
                $called       = true;
                $calledStatus = $status;
            }
        );

        $this->subject->callRequirePermission('some_nonce');

        self::assertTrue($called, 'wp_send_json_error should have been called');
        self::assertSame(403, $calledStatus, 'wp_send_json_error should be called with status 403');
    }

    public function test_requirePermission_passes_nonce_to_check_ajax_referer(): void
    {
        $capturedNonce = null;

        Functions\when('check_ajax_referer')->alias(
            static function (string $nonce, string $query = 'nonce') use (&$capturedNonce): bool {
                $capturedNonce = $nonce;
                return true;
            }
        );
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_send_json_error')->justReturn(null);

        $this->subject->callRequirePermission('my_custom_nonce');

        self::assertSame('my_custom_nonce', $capturedNonce);
    }

    // ─── requireConnectionFields: validation ──────────────────────────────────

    public function test_requireConnectionFields_returns_remote_and_adminKey_for_valid_input(): void
    {
        $_POST['remote']    = 'https://typesense.example.com';
        $_POST['admin_key'] = 'secret-admin-key';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_unslash')->returnArg(1);
        Functions\when('wp_send_json_error')->justReturn(null);

        $result = $this->subject->callRequireConnectionFields('my_nonce');

        self::assertSame('https://typesense.example.com', $result['remote']);
        self::assertSame('secret-admin-key', $result['adminKey']);
    }

    public function test_requireConnectionFields_sends_validation_error_when_remote_is_empty(): void
    {
        $_POST['remote']    = '';
        $_POST['admin_key'] = 'some-key';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_unslash')->returnArg(1);

        $errorStep = null;
        Functions\when('wp_send_json_error')->alias(
            static function (array $data) use (&$errorStep): void {
                $errorStep = $data['step'] ?? null;
            }
        );

        $this->subject->callRequireConnectionFields('my_nonce');

        self::assertSame('validation', $errorStep);
    }

    public function test_requireConnectionFields_sends_validation_error_when_admin_key_is_empty(): void
    {
        $_POST['remote']    = 'https://typesense.example.com';
        $_POST['admin_key'] = '';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_unslash')->returnArg(1);

        $errorStep = null;
        Functions\when('wp_send_json_error')->alias(
            static function (array $data) use (&$errorStep): void {
                $errorStep = $data['step'] ?? null;
            }
        );

        $this->subject->callRequireConnectionFields('my_nonce');

        self::assertSame('validation', $errorStep);
    }

    public function test_requireConnectionFields_sends_validation_error_for_url_without_host(): void
    {
        // A non-empty string that parse_url() processes but yields no host.
        $_POST['remote']    = '/relative/path';
        $_POST['admin_key'] = 'some-key';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_unslash')->returnArg(1);

        $errorStep = null;
        Functions\when('wp_send_json_error')->alias(
            static function (array $data) use (&$errorStep): void {
                // Capture the last (URL-validation) call.
                if (($data['step'] ?? '') === 'validation') {
                    $errorStep = $data['step'];
                }
            }
        );

        $this->subject->callRequireConnectionFields('my_nonce');

        self::assertSame('validation', $errorStep);
    }

    public function test_requireConnectionFields_sends_403_when_user_lacks_capability(): void
    {
        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('wp_unslash')->returnArg(1);

        $calledWith403 = false;
        Functions\when('wp_send_json_error')->alias(
            static function (array $data, int $status = 200) use (&$calledWith403): void {
                if ($status === 403) {
                    $calledWith403 = true;
                }
            }
        );

        $this->subject->callRequireConnectionFields('my_nonce');

        self::assertTrue($calledWith403, 'wp_send_json_error should be called with status 403 when user is unauthorized');
    }

    public function test_requireConnectionFields_passes_nonce_to_check_ajax_referer(): void
    {
        $_POST['remote']    = 'https://typesense.example.com';
        $_POST['admin_key'] = 'secret';

        $capturedNonce = null;
        Functions\when('check_ajax_referer')->alias(
            static function (string $nonce) use (&$capturedNonce): bool {
                $capturedNonce = $nonce;
                return true;
            }
        );
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_unslash')->returnArg(1);
        Functions\when('wp_send_json_error')->justReturn(null);

        $this->subject->callRequireConnectionFields('connection_nonce');

        self::assertSame('connection_nonce', $capturedNonce);
    }
}
