<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\CLI;

use Brain\Monkey\Functions;
use TypesenseSearch\CLI\{IndexCommand, NetworkCommand};
use TypesenseSearch\CLI\Actions\IndexAction;
use TypesenseSearch\Multisite\{NetworkSettingsRepository, SiteProvisioner};
use TypesenseSearch\Tests\TestCase;

class NetworkFlowTest extends TestCase
{
    /** @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_legacy_commands_use_setup_and_common_index_action(): void
    {
        $cli = \Mockery::mock('alias:WP_CLI');
        $cli->shouldReceive('warning')->times(3);
        $cli->shouldReceive('success')->times(4);
        $cli->shouldNotReceive('confirm');
        $setup = $this->createMock(SiteProvisioner::class);
        $setup->expects(self::exactly(3))->method('setup');
        $setup->expects(self::never())->method('prepare');
        $setup->expects(self::never())->method('activate');
        $action = $this->createMock(IndexAction::class);
        $action->expects(self::once())->method('handle')->with([], ['yes' => true, 'include-pdf' => true]);
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('get_current_blog_id')->justReturn(1);
        Functions\when('get_site')->justReturn((object) ['network_id' => 1]);
        Functions\when('get_network_option')->justReturn(['typesense-search/typesense-search.php' => 1]);
        $command = new NetworkCommand($setup, $action);
        foreach (['setup', 'prepare', 'activate'] as $operation) {
            $command([$operation], []);
        }
        $command(['index'], ['yes' => true, 'include-pdf' => true]);
    }

    /** @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider indexingModes
     */
    public function test_standard_index_completes_pending_setup_even_for_empty_content(bool $networkMode, bool $ready, bool $dryRun, int $setups): void
    {
        $cli = \Mockery::mock('alias:WP_CLI');
        $cli->shouldReceive('success')->once();
        if ($dryRun) { $cli->shouldReceive('warning')->once(); }
        $cli->shouldNotReceive('error');
        $network = $this->createMock(NetworkSettingsRepository::class);
        $network->method('isNetworkActivated')->willReturn($networkMode);
        $network->method('canUse')->willReturn($ready);
        $setup = $this->createMock(SiteProvisioner::class);
        $setup->expects(self::exactly($setups))->method('setup');
        $action = new class($network, $setup) extends IndexAction {
            public function resolvePostTypes(array $args): array { return ['page']; }
        };
        Functions\when('WP_CLI\\Utils\\get_flag_value')->alias(fn ($args, $key, $default) => $args[$key] ?? $default);
        Functions\when('get_post_types')->justReturn(['page' => (object) ['label' => 'Pages']]);
        Functions\when('wp_count_posts')->justReturn((object) ['publish' => 0]);
        $command = $this->instantiateWithoutConstructor(IndexCommand::class);
        $property = new \ReflectionProperty(IndexCommand::class, 'indexAction');
        $property->setValue($command, $action);
        $command->index([], ['yes' => true, 'dry-run' => $dryRun]);
    }

    public static function indexingModes(): array
    {
        return [
            'pending network' => [true, false, false, 1],
            'active network' => [true, true, false, 0],
            'dry run' => [true, false, true, 0],
            'single site' => [false, false, false, 0],
        ];
    }
}
