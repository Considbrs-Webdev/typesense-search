<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Multisite;

use Brain\Monkey\Functions;
use TypesenseSearch\Multisite\{CollectionNameResolver, NetworkSettingsRepository, ProvisioningGateway, SiteProvisioner};
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Tests\TestCase;

class NetworkModeTest extends TestCase
{
    private int $site = 1;
    private array $options = [];
    private array $networkOptions = [];
    private array $transients = [];
    private int $user = 42;
    private string $environment = 'development';

    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('get_current_user_id')->alias(fn () => $this->user);
        Functions\when('set_site_transient')->alias(function ($key, $value, $ttl) { $this->transients[$key] = $value; return true; });
        Functions\when('get_site_transient')->alias(fn ($key) => $this->transients[$key] ?? false);
        Functions\when('delete_site_transient')->alias(function ($key) { unset($this->transients[$key]); return true; });
        Functions\when('get_current_blog_id')->alias(fn () => $this->site);
        Functions\when('get_current_network_id')->justReturn(1);
        Functions\when('get_site')->alias(fn ($id) => (object) ['network_id' => 1, 'archived' => 0, 'spam' => 0, 'deleted' => 0]);
        Functions\when('get_home_url')->alias(fn ($id) => 'https://example.test/' . ($id === 1 ? '' : 'sub/'));
        Functions\when('wp_get_environment_type')->alias(fn () => $this->environment);
        $this->networkOptions = [
            'active_sitewide_plugins' => ['typesense-search/typesense-search.php' => 1],
            NetworkSettingsRepository::ENABLED => [1, 2],
            NetworkSettingsRepository::REMOTE => 'https://search.test',
            NetworkSettingsRepository::ADMIN_KEY => 'network-secret',
        ];
        Functions\when('get_network_option')->alias(fn ($id, $key, $default = false) => $this->networkOptions[$key] ?? $default);
        Functions\when('get_option')->alias(fn ($key, $default = false) => $this->options[$this->site][$key] ?? $default);
        Functions\when('update_option')->alias(function ($key, $value) { $this->options[$this->site][$key] = $value; return true; });
        Functions\when('add_option')->alias(function ($key, $value) {
            if (isset($this->options[$this->site][$key])) { return false; }
            $this->options[$this->site][$key] = $value;
            return true;
        });
        Functions\when('get_blog_option')->alias(fn ($id, $key, $default = false) => $this->options[$id][$key] ?? $default);
        Functions\when('delete_blog_option')->alias(function ($id, $key) { unset($this->options[$id][$key]); return true; });
        Functions\when('delete_option')->alias(function ($key) { unset($this->options[$this->site][$key]); return true; });
    }

    private function mapping(): array
    {
        return ['identity' => (new NetworkSettingsRepository())->identity(), 'collection' => 'site-' . $this->site, 'key' => 'key-' . $this->site];
    }

    public function test_disabled_site_never_falls_back_to_legacy_credentials(): void
    {
        $this->networkOptions[NetworkSettingsRepository::ENABLED] = [];
        $this->options[1] = ['typesense_search_remote' => 'https://old.test', 'typesense_search_admin_key' => 'old', 'typesense_search_index_name' => 'old', 'typesense_search_search_key' => 'old'];
        $settings = new SettingsRepository();
        self::assertFalse($settings->canUseTypesense());
        self::assertSame('', $settings->getRemote());
        self::assertSame('', $settings->getAdminKey());
        self::assertSame('', $settings->getCollectionName());
        self::assertSame('', $settings->getSearchKey());
    }

    public function test_selected_but_unprepared_site_is_disabled(): void
    {
        self::assertFalse((new SettingsRepository())->canUseTypesense());
    }

    public function test_settings_follow_a_b_a_and_environment_changes(): void
    {
        $settings = new SettingsRepository();
        $this->options[1][NetworkSettingsRepository::STATE] = ['active' => $this->mapping()];
        self::assertSame('site-1', $settings->getCollectionName());
        $this->site = 2;
        self::assertSame('', $settings->getRemote());
        $this->options[2][NetworkSettingsRepository::STATE] = ['active' => $this->mapping()];
        self::assertSame('key-2', $settings->getSearchKey());
        $this->site = 1;
        self::assertSame('key-1', $settings->getSearchKey());
        $this->environment = 'staging';
        self::assertSame('', $settings->getRemote());
    }

    public function test_local_activation_preserves_local_settings(): void
    {
        $this->networkOptions['active_sitewide_plugins'] = [];
        $this->options[1]['typesense_search_remote'] = 'https://local.test';
        self::assertTrue((new SettingsRepository())->canUseTypesense());
        self::assertSame('https://local.test', (new SettingsRepository())->getRemote());
    }

    public function test_candidate_context_is_restored_even_on_exception(): void
    {
        $network = new NetworkSettingsRepository();
        try {
            $network->withCandidate($this->mapping(), function () {
                self::assertTrue((new SettingsRepository())->canUseTypesense());
                $this->site = 2;
                self::assertFalse((new SettingsRepository())->canUseTypesense());
                $this->site = 1;
                throw new \RuntimeException('test');
            });
        } catch (\RuntimeException $e) {}
        self::assertFalse((new SettingsRepository())->canUseTypesense());
    }

    public function test_naming_preserves_unique_suffix_and_environment_when_truncated(): void
    {
        $url = 'https://' . str_repeat('x', 100) . '.test/' . str_repeat('sub/', 50);
        $one = CollectionNameResolver::name($url, 'staging', 1);
        self::assertLessThanOrEqual(128, strlen($one));
        self::assertStringEndsWith('__staging_b1', $one);
        self::assertNotSame($one, CollectionNameResolver::name($url, 'staging', 2));
        self::assertSame('example-test-sub__development_b2', CollectionNameResolver::name('https://example.test/sub/', 'development', 2));
    }

    public function test_naming_accepts_an_optional_prefix(): void
    {
        self::assertSame('eslov_example-test__development_b1', CollectionNameResolver::name('https://example.test', 'development', 1, 'eslov_'));
        self::assertSame('eslov_example-test__development_b1', CollectionNameResolver::name('https://example.test', 'development', 1, 'ESLOV'));
        self::assertSame('example-test__development_b1', CollectionNameResolver::name('https://example.test', 'development', 1, ''));
    }

    public function test_network_prefix_is_read_from_settings_and_included_in_identity(): void
    {
        $this->networkOptions[NetworkSettingsRepository::PREFIX] = 'Eslöv!';
        $network = new NetworkSettingsRepository();
        self::assertSame('eslv', $network->prefix());
        self::assertSame('eslv', $network->identity()['prefix']);
        self::assertStringStartsWith('eslv_', (new CollectionNameResolver())->resolve($network->prefix()));
    }

    public function test_network_prefix_constant_overrides_the_option(): void
    {
        $this->networkOptions[NetworkSettingsRepository::PREFIX] = 'eslov';
        $network = new class extends NetworkSettingsRepository {
            public function constant(string $name): string { return $name === 'TYPESENSE_NETWORK_PREFIX' ? 'kavlinge' : ''; }
        };
        self::assertSame('kavlinge', $network->prefix());
    }

    public function test_prepare_retries_key_failure_without_recreating_collection(): void
    {
        $gateway = new class extends ProvisioningGateway {
            public bool $created = false;
            public int $creates = 0;
            public int $keys = 0;
            public function exists(array $connection, string $name): bool { return $this->created; }
            public function create(array $connection, string $name): void { $this->created = true; $this->creates++; }
            public function owns(array $connection, string $name, string $owner): bool { return true; }
            public function key(array $connection, string $name): string {
                if (++$this->keys === 1) { throw new \RuntimeException('key failed'); }
                return 'scoped-key';
            }
            public function verify(array $connection, array $mapping): void {}
            public function sync(): void {}
        };
        $provisioner = new SiteProvisioner(new NetworkSettingsRepository(), $gateway);
        try { $provisioner->prepare(); self::fail('Expected key failure'); } catch (\RuntimeException $e) { self::assertSame('key failed', $e->getMessage()); }
        self::assertArrayNotHasKey(NetworkSettingsRepository::LOCK, $this->options[1]);
        self::assertFalse((new SettingsRepository())->canUseTypesense());
        $provisioner->prepare();
        self::assertSame(1, $gateway->creates);
        $provisioner->activate();
        self::assertSame('scoped-key', (new SettingsRepository())->getSearchKey());
        $this->networkOptions[NetworkSettingsRepository::ENABLED] = [];
        self::assertFalse((new SettingsRepository())->canUseTypesense());
        $this->networkOptions[NetworkSettingsRepository::ENABLED] = [1];
        $provisioner->prepare();
        self::assertSame(2, $gateway->keys);
        self::assertTrue((new SettingsRepository())->canUseTypesense());
    }

    public function test_prepare_recovers_active_identity_and_repairs_revoked_key(): void
    {
        $network = new NetworkSettingsRepository();
        $active = ['identity' => $network->identity(), 'collection' => (new CollectionNameResolver())->resolve(), 'owner' => 'original-owner', 'key' => 'revoked', 'prepared' => true];
        $this->options[1][NetworkSettingsRepository::STATE] = [
            'active' => $active,
            'candidate' => ['identity' => ['remote' => 'https://other.test'], 'collection' => 'other'],
        ];
        $gateway = new class extends ProvisioningGateway {
            public function exists(array $connection, string $name): bool { return true; }
            public function owns(array $connection, string $name, string $owner): bool { return $owner === 'original-owner'; }
            public function create(array $connection, string $name): void { throw new \RuntimeException('Must reuse existing collection'); }
            public function key(array $connection, string $name): string { return 'replacement'; }
            public function verify(array $connection, array $mapping): void {
                if ($mapping['key'] === 'revoked') { throw new \Typesense\Exceptions\RequestUnauthorized('revoked'); }
            }
            public function sync(): void {}
        };
        $provisioner = new SiteProvisioner($network, $gateway);
        $provisioner->prepare();
        self::assertSame($active, $network->state()['active']);
        self::assertSame('replacement', $network->state()['candidate']['key']);
        $provisioner->activate();
        self::assertSame('replacement', (new SettingsRepository())->getSearchKey());
        self::assertArrayNotHasKey(NetworkSettingsRepository::LOCK, $this->options[1]);
    }

    public function test_setup_retries_sync_failure_and_activates_without_indexing(): void
    {
        $network = new NetworkSettingsRepository();
        $gateway = new class extends ProvisioningGateway {
            public int $creates = 0;
            public int $keys = 0;
            public int $syncs = 0;
            public function exists(array $connection, string $name): bool { return $this->creates > 0; }
            public function create(array $connection, string $name): void { $this->creates++; }
            public function owns(array $connection, string $name, string $owner): bool { return true; }
            public function key(array $connection, string $name): string { $this->keys++; return 'scoped-key'; }
            public function verify(array $connection, array $mapping): void {}
            public function sync(): void {
                // The same lock covers both creation and activation.
                if (!get_option(NetworkSettingsRepository::LOCK)) { throw new \LogicException('Missing setup lock'); }
                if (++$this->syncs === 1) { throw new \RuntimeException('sync failed'); }
            }
        };
        $provisioner = new SiteProvisioner($network, $gateway);
        try {
            $provisioner->setup();
            self::fail('Expected sync failure');
        } catch (\RuntimeException $e) {
            self::assertSame('sync failed', $e->getMessage());
        }
        self::assertFalse($network->canUse());
        self::assertTrue($network->state()['candidate']['prepared']);
        self::assertArrayNotHasKey(NetworkSettingsRepository::LOCK, $this->options[1]);
        $provisioner->setup();
        self::assertTrue($network->canUse());
        self::assertSame('scoped-key', (new SettingsRepository())->getSearchKey());
        $active = $network->state()['active'];
        $provisioner->setup();
        self::assertSame($active, $network->state()['active']);
        self::assertSame([], $network->state()['previous']);
        self::assertSame(1, $gateway->creates);
        self::assertSame(1, $gateway->keys);
        self::assertArrayNotHasKey(NetworkSettingsRepository::LOCK, $this->options[1]);

        // Indexing failures must not change runtime eligibility.
        try {
            $provisioner->index(function () { throw new \RuntimeException('index interrupted'); });
        } catch (\RuntimeException $e) {
            self::assertSame('index interrupted', $e->getMessage());
        }
        self::assertSame($active, $network->state()['active']);
        self::assertTrue($network->canUse());
        self::assertArrayNotHasKey(NetworkSettingsRepository::LOCK, $this->options[1]);
    }

    public function test_setup_respects_existing_lock(): void
    {
        $this->options[1][NetworkSettingsRepository::LOCK] = 'another-process';
        try {
            (new SiteProvisioner())->setup();
            self::fail('Expected lock conflict');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('holds this site lock', $e->getMessage());
        }
        self::assertSame('another-process', $this->options[1][NetworkSettingsRepository::LOCK]);
        self::assertArrayNotHasKey(NetworkSettingsRepository::STATE, $this->options[1]);
    }

    public function test_foreign_collection_is_not_adopted(): void
    {
        $gateway = new class extends ProvisioningGateway {
            public function exists(array $connection, string $name): bool { return true; }
        };
        $this->expectExceptionMessage('not owned');
        (new SiteProvisioner(new NetworkSettingsRepository(), $gateway))->prepare();
    }

    public function test_existing_lock_prevents_provisioning(): void
    {
        $this->options[1][NetworkSettingsRepository::LOCK] = time();
        $this->expectExceptionMessage('holds this site lock');
        (new SiteProvisioner())->prepare();
    }

    public function test_old_ajax_connection_action_is_blocked_for_local_admin(): void
    {
        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_send_json_error')->alias(function ($body, $status) {
            self::assertSame(403, $status);
            throw new \RuntimeException('blocked');
        });
        $subject = new class {
            use \TypesenseSearch\Admin\Ajax\AjaxHelpers;
            public function run(): void { $this->requirePermission('typesense_fix_search_key'); }
        };
        $this->expectExceptionMessage('blocked');
        $subject->run();
    }
    public function test_network_constants_do_not_override_disabled_policy(): void
    {
        $this->networkOptions[NetworkSettingsRepository::ENABLED] = [];
        $network = new class extends NetworkSettingsRepository {
            public function constant(string $name): string { return $name === 'TYPESENSE_HOST' ? 'https://constant.test' : ''; }
        };
        self::assertSame('https://constant.test', $network->connection()['remote']);
        self::assertFalse($network->canUse());
    }

    public function test_global_collection_constant_blocks_network_mode(): void
    {
        $this->options[1][NetworkSettingsRepository::STATE] = ['active' => $this->mapping()];
        $network = new class extends NetworkSettingsRepository {
            public function constant(string $name): string { return $name === 'TYPESENSE_COLLECTION' ? 'shared' : ''; }
        };
        self::assertTrue($network->conflict());
        self::assertFalse($network->canUse());
    }

    public function test_settings_persistence_failure_keeps_old_mapping(): void
    {
        $active = $this->mapping();
        $this->options[1][NetworkSettingsRepository::STATE] = ['active' => $active];
        Functions\when('update_option')->justReturn(false);
        $gateway = new class extends ProvisioningGateway {
            public function exists(array $connection, string $name): bool { return false; }
            public function create(array $connection, string $name): void { throw new \LogicException('Must not create before state persisted'); }
        };
        try {
            (new SiteProvisioner(new NetworkSettingsRepository(), $gateway))->prepare();
            self::fail('Expected persistence error');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('save provisioning state', $e->getMessage());
        }
        self::assertSame($active, $this->options[1][NetworkSettingsRepository::STATE]['active']);
        self::assertArrayNotHasKey(NetworkSettingsRepository::LOCK, $this->options[1]);
    }

    public function test_network_save_rejects_local_administrator_before_writes(): void
    {
        Functions\when('current_user_can')->alias(fn ($cap) => $cap === 'manage_options');
        Functions\when('wp_die')->alias(function ($message, $title, $args) {
            self::assertSame(403, $args['response']);
            throw new \RuntimeException('unauthorized');
        });
        Functions\when('esc_html__')->returnArg(1);
        $this->expectExceptionMessage('unauthorized');
        (new \TypesenseSearch\Admin\NetworkSettingsPage())->save();
    }

    public function test_site_action_rejects_another_target_before_provisioning(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('esc_html__')->returnArg(1);
        Functions\when('wp_die')->alias(function ($message, $title, $args) {
            self::assertSame(403, $args['response']);
            throw new \RuntimeException('wrong site');
        });
        $_POST = ['site_id' => 2, 'network_id' => 1];
        try {
            (new \TypesenseSearch\Admin\NetworkSettingsPage())->siteAction();
            self::fail('Expected context error');
        } catch (\RuntimeException $e) {
            self::assertSame('wrong site', $e->getMessage());
        } finally { $_POST = []; }
    }

    public function test_shared_core_admin_url_targets_subsite_route(): void
    {
        Functions\when('get_admin_url')->justReturn('https://example.test/wp/wp-admin/admin-post.php');
        Functions\when('is_subdomain_install')->justReturn(false);
        Functions\when('get_main_site_id')->justReturn(1);
        Functions\when('get_site_url')->justReturn('https://example.test/wp');
        Functions\when('trailingslashit')->alias(fn ($s) => rtrim($s, '/') . '/');
        Functions\when('apply_filters')->alias(fn ($name, $url) => $url);
        self::assertSame('https://example.test/sub/wp-admin/admin-post.php', \TypesenseSearch\Multisite\SiteUrls::admin(2, 'admin-post.php'));
        self::assertSame('https://example.test/wp/wp-admin/admin-post.php', \TypesenseSearch\Multisite\SiteUrls::admin(1, 'admin-post.php'));
    }


    public function test_dispatch_rejects_unprivileged_user(): void
    {
        Functions\when('current_user_can')->justReturn(false);
        Functions\expect('wp_remote_post')->never();
        $this->expectExceptionMessage('Unauthorized setup request');
        (new \TypesenseSearch\Multisite\SetupDispatcher())->dispatch([1]);
    }


    public function test_setup_activates_legacy_candidate_and_records_status(): void
    {
        $network = new NetworkSettingsRepository();
        $candidate = ['identity' => $network->identity(), 'collection' => (new CollectionNameResolver())->resolve(),
            'owner' => 'legacy', 'key' => 'saved-key', 'prepared' => true];
        $this->options[1][NetworkSettingsRepository::STATE] = ['candidate' => $candidate];
        $gateway = new class extends ProvisioningGateway {
            public function exists(array $connection, string $name): bool { return true; }
            public function owns(array $connection, string $name, string $owner): bool { return $owner === 'legacy'; }
            public function key(array $connection, string $name): string { throw new \LogicException('Must reuse key'); }
            public function verify(array $connection, array $mapping): void {}
            public function sync(): void {}
        };
        (new SiteProvisioner($network, $gateway))->setup();
        self::assertSame($candidate, $network->state()['active']);
        self::assertTrue($network->canUse());
        self::assertSame('ready', \TypesenseSearch\Multisite\SetupDispatcher::status(1)['status']);
        $this->environment = 'staging';
        self::assertFalse($network->canUse());
        try { (new SiteProvisioner($network, $gateway))->setup(); } catch (\RuntimeException $e) {}
        self::assertSame($candidate, $network->state()['active']);
        self::assertSame('error', \TypesenseSearch\Multisite\SetupDispatcher::status(1)['status']);
    }



    public function test_configuration_errors_distinguish_disabled_missing_setup_and_changed_identity(): void
    {
        $network = new NetworkSettingsRepository();
        self::assertStringContainsString('Setup is incomplete', $network->unavailableReason());
        $this->options[1][NetworkSettingsRepository::STATE] = ['active' => $this->mapping()];
        $this->environment = 'staging';
        self::assertFalse($network->canUse());
        self::assertStringContainsString('has changed', $network->unavailableReason());
        $this->networkOptions[NetworkSettingsRepository::ENABLED] = [];
        self::assertStringContainsString('disabled', $network->unavailableReason());
        $this->networkOptions[NetworkSettingsRepository::ENABLED] = [1];
        $this->networkOptions[NetworkSettingsRepository::ADMIN_KEY] = '';
        self::assertStringContainsString('Configure the network connection', $network->unavailableReason());
    }



    private function deletionFixture(): array
    {
        $network = new NetworkSettingsRepository();
        $mapping = $this->mapping() + ['owner' => 'owner'];
        $this->options[1][NetworkSettingsRepository::STATE] = ['active' => $mapping, 'candidate' => $mapping];
        $this->networkOptions[NetworkSettingsRepository::ENABLED] = [];
        $fingerprint = \TypesenseSearch\Admin\NetworkSettingsPage::checkFingerprint($network->connection(), $mapping);
        return [$network, $mapping, $fingerprint];
    }

    public function test_delete_removes_only_reviewed_mapping_and_preserves_local_settings(): void
    {
        [$network, $mapping, $fingerprint] = $this->deletionFixture();
        $other = ['identity' => ['environment' => 'production'], 'collection' => 'other'];
        $this->options[1][NetworkSettingsRepository::STATE]['previous'] = $other;
        $this->options[1]['typesense_search_index_name'] = 'legacy-local';
        $gateway = $this->createMock(ProvisioningGateway::class);
        $gateway->method('exists')->willReturn(true);
        $gateway->method('owns')->willReturn(true);
        $gateway->expects(self::once())->method('deletionKeyIds')->with($network->connection(), ['active' => $mapping, 'candidate' => $mapping])->willReturn(['7']);
        $gateway->expects(self::once())->method('deleteKey')->with($network->connection(), '7');
        $gateway->expects(self::once())->method('deleteCollection')->with($network->connection(), $mapping['collection']);
        (new SiteProvisioner($network, $gateway))->delete($fingerprint);
        self::assertSame(['previous' => $other], $network->state());
        self::assertSame('legacy-local', $this->options[1]['typesense_search_index_name']);
        self::assertArrayNotHasKey(NetworkSettingsRepository::LOCK, $this->options[1]);
    }

    public function test_delete_rejects_enabled_site_and_foreign_ownership(): void
    {
        [$network, $mapping, $fingerprint] = $this->deletionFixture();
        $gateway = $this->createMock(ProvisioningGateway::class);
        $gateway->method('exists')->willReturn(true);
        $gateway->method('owns')->willReturn(false);
        $gateway->expects(self::never())->method('deleteCollection');
        $gateway->expects(self::never())->method('deleteKey');
        $this->networkOptions[NetworkSettingsRepository::ENABLED] = [1];
        try { (new SiteProvisioner($network, $gateway))->delete($fingerprint); self::fail(); }
        catch (\TypesenseSearch\Multisite\SetupException $e) { self::assertStringContainsString('Disable the site', $e->getMessage()); }
        $this->networkOptions[NetworkSettingsRepository::ENABLED] = [];
        try { (new SiteProvisioner($network, $gateway))->delete($fingerprint); self::fail(); }
        catch (\TypesenseSearch\Multisite\SetupException $e) { self::assertStringContainsString('does not belong', $e->getMessage()); }
        self::assertSame($mapping, $network->state()['active']);
    }

    public function test_delete_rejects_changed_connection_or_stale_confirmation(): void
    {
        [$network, $mapping, $fingerprint] = $this->deletionFixture();
        $gateway = $this->createMock(ProvisioningGateway::class);
        $gateway->expects(self::never())->method('exists');
        try { (new SiteProvisioner($network, $gateway))->delete('stale'); self::fail(); }
        catch (\TypesenseSearch\Multisite\SetupException $e) { self::assertStringContainsString('has changed', $e->getMessage()); }
        $this->networkOptions[NetworkSettingsRepository::REMOTE] = 'https://other.test';
        $this->expectException(\TypesenseSearch\Multisite\SetupException::class);
        (new SiteProvisioner($network, $gateway))->delete($fingerprint);
    }

    public function test_partial_delete_preserves_state_for_retry(): void
    {
        [$network, $mapping, $fingerprint] = $this->deletionFixture();
        $gateway = new class extends ProvisioningGateway {
            public int $attempts = 0;
            public function exists(array $connection, string $name): bool { return true; }
            public function owns(array $connection, string $name, string $owner): bool { return true; }
            public function deletionKeyIds(array $connection, array $mappings): array { return []; }
            public function deleteCollection(array $connection, string $name): void {
                if (++$this->attempts === 1) { throw new \RuntimeException('network failed'); }
            }
        };
        $provisioner = new SiteProvisioner($network, $gateway);
        try { $provisioner->delete($fingerprint); self::fail(); } catch (\RuntimeException $e) {}
        self::assertSame($mapping, $network->state()['active']);
        self::assertArrayNotHasKey(NetworkSettingsRepository::LOCK, $this->options[1]);
        $provisioner->delete($fingerprint);
        self::assertSame([], $network->state());
    }

    public function test_delete_respects_existing_setup_lock(): void
    {
        [$network, $mapping, $fingerprint] = $this->deletionFixture();
        $this->options[1][NetworkSettingsRepository::LOCK] = 'running';
        $gateway = $this->createMock(ProvisioningGateway::class);
        $gateway->expects(self::never())->method('exists');
        $this->expectExceptionMessage('holds this site lock');
        (new SiteProvisioner($network, $gateway))->delete($fingerprint);
    }

    public function test_deletion_keys_require_unique_prefix_and_exact_scope(): void
    {
        $mapping = ['key' => 'abcd-full-secret', 'collection' => 'site-1'];
        $key = ['id' => 7, 'value_prefix' => 'abcd', 'collections' => ['site-1'],
            'actions' => ['documents:search'], 'description' => 'Search-only key for collection: site-1'];
        self::assertSame(['7'], ProvisioningGateway::matchDeletionKeys([$key], [$mapping, $mapping]));
        self::assertSame([], ProvisioningGateway::matchDeletionKeys([], [$mapping]));
        foreach ([[$key, array_merge($key, ['id' => 8])], [array_merge($key, ['collections' => ['*']])]] as $keys) {
            try { ProvisioningGateway::matchDeletionKeys($keys, [$mapping]); self::fail(); }
            catch (\TypesenseSearch\Multisite\SetupException $e) { self::assertStringContainsString('cannot be identified uniquely', $e->getMessage()); }
        }
    }

    public function test_setup_queue_uses_browser_and_is_scoped_to_user_and_current_step(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\expect('wp_remote_post')->never();
        $dispatcher = new \TypesenseSearch\Multisite\SetupDispatcher();
        $id = $dispatcher->dispatch([1, 2, 2, 99]);
        self::assertSame([1, 2], $dispatcher::pending($id)['sites']);
        self::assertTrue($dispatcher::accepts($id, 1));
        self::assertFalse($dispatcher::accepts($id, 2));
        self::assertFalse($dispatcher::accepts('old', 1));
        $this->user = 43;
        self::assertSame([], $dispatcher::pending($id));
        $this->user = 42;
        $dispatcher::complete($id, 1);
        self::assertFalse($dispatcher::accepts($id, 1));
        self::assertTrue($dispatcher::accepts($id, 2));
        $dispatcher::complete($id, 2);
        self::assertSame([], $dispatcher::pending($id));
    }

    public function test_new_save_replaces_old_sequence_and_invalidates_legacy_tokens(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        $dispatcher = new \TypesenseSearch\Multisite\SetupDispatcher();
        $this->options[1][$dispatcher::JOB] = ['hash' => 'old'];
        $old = $dispatcher->dispatch([1]);
        $new = $dispatcher->dispatch([2], 'connection');
        self::assertArrayNotHasKey($dispatcher::JOB, $this->options[1]);
        self::assertSame([], $dispatcher::pending($old));
        self::assertSame('connection', $dispatcher::pending($new)['tab']);
        $dispatcher::complete($old, 1);
        self::assertTrue($dispatcher::accepts($new, 2));
        self::assertSame('', $dispatcher->dispatch([]));
        self::assertSame([], $dispatcher::pending($new));
    }

}
