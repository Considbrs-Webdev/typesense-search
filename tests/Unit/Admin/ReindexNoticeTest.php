<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use TypesenseSearch\Admin\ReindexNotice;
use TypesenseSearch\Tests\TestCase;

class ReindexNoticeTest extends TestCase
{
    public function test_register_watches_url_affecting_options(): void
    {
        (new ReindexNotice())->register();

        foreach (['permalink_structure', 'home', 'siteurl', 'page_on_front', 'show_on_front'] as $option) {
            self::assertNotFalse(has_action('update_option_' . $option));
        }
    }

    public function test_flag_sets_the_option(): void
    {
        Functions\expect('update_option')->once()->with(ReindexNotice::OPTION_FLAG, '1', false);

        (new ReindexNotice())->flag();

        $this->addToAssertionCount(1);
    }

    public function test_notice_is_hidden_without_flag(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_option')->justReturn(false);

        ob_start();
        (new ReindexNotice())->renderNotice();

        self::assertSame('', ob_get_clean());
    }

    public function test_notice_is_shown_when_flagged(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_option')->justReturn('1');
        Functions\when('wp_nonce_url')->returnArg();
        Functions\when('admin_url')->returnArg();
        Functions\when('esc_html__')->returnArg();
        Functions\when('esc_url')->returnArg();

        ob_start();
        (new ReindexNotice())->renderNotice();

        self::assertStringContainsString('notice-warning', (string) ob_get_clean());
    }
}
