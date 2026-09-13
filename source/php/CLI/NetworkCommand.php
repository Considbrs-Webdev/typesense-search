<?php

namespace TypesenseSearch\CLI;

use TypesenseSearch\Multisite\SiteProvisioner;

/** Compatibility commands for setup and indexing in the site's own --url context. */
class NetworkCommand
{
    public function __construct(
        private SiteProvisioner $provisioner = new SiteProvisioner(),
        private Actions\IndexAction $indexAction = new Actions\IndexAction(),
    ) {
    }

    /**
     * Manage the current site's network collection.
     *
     * ## OPTIONS
     *
     * <operation>
     * : setup, prepare, index, or activate. prepare/activate are compatibility aliases for setup.
     *
     * [--yes]
     * : Skip the indexing confirmation prompt.
     *
     * [--include-pdf]
     * : Include PDFs when indexing the site.
     *
     * [--include-external]
     * : Include registered external strategies when indexing the site.
     *
     * [--batch-size=<number>]
     * : Index batch size.
     *
     * ## EXAMPLES
     *
     *     wp --url=https://example.com/subsite/ typesense network setup
     *     wp --url=https://example.com/subsite/ typesense network index --yes
     *     wp --url=https://example.com/subsite/ typesense network activate --yes
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $provisioner = $this->provisioner;
        try {
            switch ($args[0] ?? '') {
                case 'setup':
                case 'prepare':
                case 'activate':
                    if ($args[0] !== 'setup') {
                        \WP_CLI::warning('Compatibility alias: setup now creates and activates the index without requiring indexing or review.');
                    }
                    $provisioner->setup();
                    break;
                case 'index':
                    if (!(new \TypesenseSearch\Multisite\NetworkSettingsRepository())->isNetworkActivated()) {
                        throw new \TypesenseSearch\Multisite\SetupException(__('Network mode is not enabled. Use typesense index.', 'typesense-search'));
                    }
                    \WP_CLI::warning('Compatibility alias: use typesense index for initial and recurring indexing.');
                    $this->indexAction->handle([], $assocArgs);
                    break;
                default:
                    \WP_CLI::error('Use setup or index (prepare and activate remain setup aliases).');
            }
            \WP_CLI::success('Network collection operation completed.');
        } catch (\Throwable $e) {
            \WP_CLI::error(\TypesenseSearch\Multisite\SetupException::describe($e));
        }
    }
}
