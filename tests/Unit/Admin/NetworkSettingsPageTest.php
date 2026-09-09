<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Mockery;
use TypesenseSearch\Admin\NetworkSettingsPage;
use TypesenseSearch\Multisite\{NetworkSettingsRepository, SetupDispatcher};
use TypesenseSearch\Tests\TestCase;

class NetworkSettingsPageTest extends TestCase
{
    private array $options = [];
    private array $connection = ['remote' => 'https://search.test', 'admin_key' => 'secret', 'frontend_host' => ''];
    private NetworkSettingsRepository $network;
    private NetworkSettingsPage $page;

    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('get_current_user_id')->justReturn(1);
        Functions\when('delete_site_transient')->justReturn(true);
        $this->page = new NetworkSettingsPage();
        $this->network = Mockery::mock(NetworkSettingsRepository::class);
        $this->network->shouldReceive('connection')->andReturnUsing(fn () => $this->connection);
        $this->network->shouldReceive('networkId')->andReturn(1);
        $this->network->shouldReceive('conflict')->andReturn(false);
        Functions\when('get_home_url')->justReturn('https://site.test/');
        Functions\when('wp_get_environment_type')->justReturn('local');
        Functions\when('get_blog_option')->alias(fn ($id, $name, $default = false) => $this->options[$name] ?? $default);
    }

    private function mapping(): array
    {
        return ['identity' => ['home' => 'https://site.test', 'environment' => 'local',
            'remote' => 'https://search.test', 'network' => 1, 'site' => 2],
            'collection' => 'site_index', 'key' => 'site-secret'];
    }

    public function test_selected_site_without_setup_is_pending_and_has_no_index(): void
    {
        $row = $this->page->siteStatus(2, true, $this->network);
        self::assertSame('pending', $row['tone']);
        self::assertFalse($row['ready']);
        self::assertFalse($row['retry']);
        self::assertSame('', $row['collection']);
    }

    public function test_expired_setup_offers_retry_without_treating_saved_name_as_existing_index(): void
    {
        $this->options[SetupDispatcher::STATUS] = ['status' => 'running', 'time' => time() - 301];
        $this->options[NetworkSettingsRepository::STATE] = ['candidate' => ['collection' => 'planned_index']];
        $row = $this->page->siteStatus(2, true, $this->network);
        self::assertSame('error', $row['tone']);
        self::assertTrue($row['retry']);
        self::assertSame('planned_index', $row['collection']);
        self::assertSame([], $row['check']);
    }

    public function test_pending_request_is_not_presented_as_running_setup(): void
    {
        $this->options[NetworkSettingsRepository::STATE] = ['active' => $this->mapping()];
        $this->options[SetupDispatcher::STATUS] = ['status' => 'pending', 'time' => time()];
        $row = $this->page->siteStatus(2, true, $this->network);
        self::assertTrue($row['ready']);
        self::assertStringContainsString('Setup did not finish', $row['detail']);
        self::assertTrue($row['retry']);
        self::assertStringNotContainsString('in progress', $row['detail']);
    }

    public function test_running_setup_does_not_offer_a_duplicate_retry(): void
    {
        $this->options[SetupDispatcher::STATUS] = ['status' => 'running', 'time' => time()];
        $row = $this->page->siteStatus(2, true, $this->network);
        self::assertSame('pending', $row['tone']);
        self::assertFalse($row['retry']);
        self::assertStringContainsString('in progress', $row['detail']);
    }

    public function test_failed_repeat_setup_keeps_active_configuration_and_offers_retry(): void
    {
        $this->options[NetworkSettingsRepository::STATE] = ['active' => $this->mapping()];
        $this->options[SetupDispatcher::STATUS] = ['status' => 'error', 'message' => 'Synchronization failed.'];
        $row = $this->page->siteStatus(2, true, $this->network);
        self::assertTrue($row['ready']);
        self::assertSame('active', $row['tone']);
        self::assertTrue($row['retry']);
        self::assertStringContainsString('Synchronization failed.', $row['detail']);
    }

    public function test_disabled_site_preserves_index_but_has_no_retry_or_current_check(): void
    {
        $this->options[NetworkSettingsRepository::STATE] = ['active' => $this->mapping()];
        $this->options[SetupDispatcher::STATUS] = ['status' => 'error'];
        $row = $this->page->siteStatus(2, false, $this->network);
        self::assertSame('disabled', $row['tone']);
        self::assertFalse($row['ready']);
        self::assertFalse($row['retry']);
        self::assertSame('site_index', $row['collection']);
    }

    public function test_status_check_is_separate_from_activation_and_invalidated_by_credential_change(): void
    {
        $mapping = $this->mapping();
        $this->options[NetworkSettingsRepository::STATE] = ['active' => $mapping];
        $this->options[NetworkSettingsPage::CHECK] = ['fingerprint' => NetworkSettingsPage::checkFingerprint($this->connection, $mapping),
            'time' => time(), 'success' => false, 'message' => 'Connection failed.'];
        $row = $this->page->siteStatus(2, true, $this->network);
        self::assertTrue($row['ready']);
        self::assertFalse($row['check']['success']);
        $this->connection['admin_key'] = 'changed';
        self::assertSame([], $this->page->siteStatus(2, true, $this->network)['check']);
    }

    public function test_changed_environment_and_missing_collection_are_not_active(): void
    {
        $mapping = $this->mapping();
        $mapping['identity']['environment'] = 'production';
        $this->options[NetworkSettingsRepository::STATE] = ['active' => $mapping];
        self::assertFalse($this->page->siteStatus(2, true, $this->network)['ready']);
        $mapping = $this->mapping();
        unset($mapping['collection']);
        $this->options[NetworkSettingsRepository::STATE] = ['active' => $mapping];
        self::assertFalse($this->page->siteStatus(2, true, $this->network)['ready']);
    }

    public function test_site_status_action_returns_to_sites_tab_and_persists_failed_check(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('get_current_blog_id')->justReturn(2);
        Functions\when('get_current_network_id')->justReturn(1);
        Functions\when('get_site')->justReturn((object) ['network_id' => 1, 'archived' => 0, 'spam' => 0, 'deleted' => 0]);
        Functions\when('get_network_option')->alias(static fn ($id, $name, $default = false) =>
            $name === 'active_sitewide_plugins' ? ['typesense-search/typesense-search.php' => 1] : $default);
        Functions\when('get_option')->justReturn([]);
        Functions\when('check_admin_referer')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(1);
        Functions\expect('update_option')->once()->with(NetworkSettingsPage::CHECK, Mockery::on(static fn ($check) =>
            $check['success'] === false && isset($check['time'], $check['fingerprint'])), false);
        Functions\expect('set_site_transient')->once()->with('typesense_network_notice_1', Mockery::type('array'), 120);
        Functions\when('network_admin_url')->returnArg();
        Functions\expect('wp_safe_redirect')->once()->with('settings.php?page=typesense-network&tab=sites')
            ->andThrow(new \LogicException('redirect'));
        $_POST = ['site_id' => 2, 'network_id' => 1, 'operation' => 'status', 'tab' => 'status'];
        $this->expectExceptionMessage('redirect');
        $this->page->siteAction();
    }

    public function test_saving_site_selection_returns_to_sites_tab(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('get_current_blog_id')->justReturn(2);
        Functions\when('get_site')->justReturn((object) ['network_id' => 1]);
        Functions\when('get_network_option')->alias(static fn ($id, $name, $default = false) =>
            $name === 'active_sitewide_plugins' ? ['typesense-search/typesense-search.php' => 1] : $default);
        Functions\when('check_admin_referer')->justReturn(true);
        Functions\expect('update_network_option')->once()->with(1, NetworkSettingsRepository::ENABLED, []);
        Functions\when('network_admin_url')->returnArg();
        Functions\expect('wp_safe_redirect')->once()->with('settings.php?page=typesense-network&tab=sites&saved=1')
            ->andThrow(new \LogicException('redirect'));
        $_POST = ['section' => 'sites', 'sites' => []];
        $this->expectExceptionMessage('redirect');
        $this->page->save();
    }

    public function test_failed_shared_connection_check_returns_to_connection_tab(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('get_current_blog_id')->justReturn(2);
        Functions\when('get_site')->justReturn((object) ['network_id' => 1]);
        Functions\when('get_network_option')->alias(static fn ($id, $name, $default = false) =>
            $name === 'active_sitewide_plugins' ? ['typesense-search/typesense-search.php' => 1] : $default);
        Functions\when('check_admin_referer')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(1);
        Functions\expect('set_site_transient')->once()->with('typesense_network_notice_1', Mockery::on(static fn ($notice) => !$notice['success']), 120);
        Functions\when('network_admin_url')->returnArg();
        Functions\expect('wp_safe_redirect')->once()->with('settings.php?page=typesense-network&tab=connection')
            ->andThrow(new \LogicException('redirect'));
        $_POST = ['section' => 'status'];
        $this->expectExceptionMessage('redirect');
        $this->page->save();
    }

    /** @dataProvider panelSelection */
    public function test_panel_renders_automatic_flow_without_manual_activation_or_secret(bool $enabled): void
    {
        $this->options[NetworkSettingsRepository::STATE] = ['active' => $this->mapping() + ['owner' => 'owner'], 'candidate' => $this->mapping() + ['owner' => 'owner']];
        $this->options[SetupDispatcher::STATUS] = ['status' => 'error', 'message' => '<script>bad</script>'];
        foreach (['esc_html', 'esc_attr', 'esc_url'] as $function) {
            Functions\when($function)->alias(static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES));
        }
        Functions\when('esc_html_e')->alias(static function ($value) { echo htmlspecialchars($value, ENT_QUOTES); });
        Functions\when('network_admin_url')->returnArg();
        Functions\when('get_current_user_id')->justReturn(1);
        Functions\when('get_site_transient')->justReturn(false);
        Functions\when('get_admin_url')->justReturn('https://site.test/wp-admin/');
        Functions\when('get_site')->justReturn(null);
        Functions\when('apply_filters')->alias(static fn ($hook, $value) => $value);
        Functions\when('wp_nonce_field')->justReturn('');
        Functions\when('checked')->justReturn('');
        Functions\when('disabled')->justReturn('');
        $_GET = [];
        $network = $this->network;
        $connection = $this->connection;
        $render = function () use ($network, $connection, $enabled): void {
            $tab = 'sites';
            $networkId = 1;
            $selected = $enabled ? [2] : [];
            $sites = [(object) ['blog_id' => 2]];
            include dirname(__DIR__, 3) . '/views/admin/network/settings.php';
        };
        ob_start();
        try {
            $render->call($this->page);
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if (!$enabled) {
            self::assertStringContainsString('value="delete"', $html);
            self::assertStringContainsString('name="confirm_delete" value="1" required', $html);
            self::assertStringContainsString('name="delete_fingerprint"', $html);
            self::assertStringNotContainsString('site-secret', $html);
            self::assertStringNotContainsString('value="setup"', $html);
            return;
        }
        self::assertStringNotContainsString('value="delete"', $html);
        self::assertStringContainsString('value="setup"', $html);
        self::assertStringContainsString('name="tab" value="sites"', $html);
        self::assertStringContainsString(' typesense index --yes', $html);
        self::assertStringContainsString('Saved index name.', $html);
        self::assertStringNotContainsString('value="activate"', $html);
        self::assertStringNotContainsString('value="prepare"', $html);
        self::assertStringNotContainsString('reviewed', $html);
        self::assertStringNotContainsString('Candidate:', $html);
        self::assertStringNotContainsString('site-secret', $html);
        self::assertStringNotContainsString('&amp;tab=status', $html);
        preg_match_all('/<td>(.*?)<\/td>/s', $html, $cells);
        self::assertStringContainsString('Indexing with WP-CLI', $cells[1][3]);
        self::assertStringNotContainsString('Indexing with WP-CLI', $cells[1][2]);
        self::assertStringNotContainsString('<script>', $html);
    }

    /** @dataProvider setupResults */
    public function test_authenticated_sequence_advances_after_success_or_failure(bool $fails): void
    {
        $setup = Mockery::mock(\TypesenseSearch\Multisite\SiteProvisioner::class);
        if ($fails) {
            $setup->shouldReceive('setup')->once()->andThrow(new \RuntimeException('private server error'));
        } else {
            $setup->shouldReceive('setup')->once();
        }
        $page = new NetworkSettingsPage($setup);
        $run = ['id' => 'run', 'network' => 1, 'sites' => [2, 3], 'tab' => 'sites', 'total' => 2];
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('get_current_blog_id')->justReturn(2);
        Functions\when('get_current_network_id')->justReturn(1);
        Functions\when('get_site')->justReturn((object) ['network_id' => 1]);
        Functions\when('get_network_option')->alias(static fn ($id, $name, $default = false) =>
            $name === 'active_sitewide_plugins' ? ['typesense-search/typesense-search.php' => 1] : $default);
        Functions\when('get_option')->justReturn([]);
        Functions\expect('check_admin_referer')->once()->with(NetworkSettingsPage::ACTION . '_2');
        Functions\when('get_site_transient')->alias(fn () => $run);
        Functions\expect('set_site_transient')->once()->with(SetupDispatcher::RUN . '1', Mockery::on(static fn ($value) => $value['sites'] === [3]), 3600)->andReturn(true);
        Functions\expect('set_site_transient')->once()->with('typesense_network_notice_1', Mockery::on(static fn ($value) => $value['success'] === !$fails && !str_contains($value['message'], 'private')), 120);
        if ($fails) {
            Functions\expect('update_option')->once()->with(SetupDispatcher::STATUS, Mockery::on(static fn ($value) => $value['status'] === 'error'), false);
        }
        Functions\when('network_admin_url')->returnArg();
        Functions\expect('wp_safe_redirect')->once()->with('settings.php?page=typesense-network&tab=sites&setup_run=run')->andThrow(new \LogicException('redirect'));
        $_POST = ['site_id' => 2, 'network_id' => 1, 'operation' => 'setup', 'setup_run' => 'run'];
        $this->expectExceptionMessage('redirect');
        $page->siteAction();
    }

    public static function setupResults(): array
    {
        return [[false], [true]];
    }

    public static function panelSelection(): array
    {
        return [[true], [false]];
    }

    public function test_delete_is_available_only_for_disabled_owned_current_mapping(): void
    {
        $mapping = $this->mapping() + ['owner' => 'owner'];
        $this->options[NetworkSettingsRepository::STATE] = ['active' => $mapping];
        self::assertFalse($this->page->siteStatus(2, true, $this->network)['deletable']);
        $row = $this->page->siteStatus(2, false, $this->network);
        self::assertTrue($row['deletable']);
        self::assertSame(NetworkSettingsPage::checkFingerprint($this->connection, $mapping), $row['delete_fingerprint']);
        $mapping['identity']['environment'] = 'production';
        $this->options[NetworkSettingsRepository::STATE] = ['active' => $mapping];
        self::assertFalse($this->page->siteStatus(2, false, $this->network)['deletable']);
    }

    public function test_return_tabs_are_allowlisted(): void
    {
        self::assertSame('sites', NetworkSettingsPage::tab('sites'));
        self::assertSame('sites', NetworkSettingsPage::tab('status'));
        self::assertSame('connection', NetworkSettingsPage::tab('https://elsewhere.test'));
        self::assertSame('connection', NetworkSettingsPage::tab(['status']));
    }
}
