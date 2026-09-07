<?php

namespace TypesenseSearch\CLI;

use TypesenseSearch\Multisite\SiteProvisioner;

/** Prepare, index and activate a network site's candidate in its own --url context. */
class NetworkCommand
{
    /**
     * Manage the current site's network collection.
     *
     * ## OPTIONS
     *
     * <operation>
     * : prepare, index, or activate.
     *
     * [--yes]
     * : Confirm activation after reviewing the candidate content.
     *
     * [--include-pdf]
     * : Include PDFs when indexing the candidate.
     *
     * [--include-external]
     * : Include registered external strategies when indexing the candidate.
     *
     * [--batch-size=<number>]
     * : Index batch size.
     *
     * ## EXAMPLES
     *
     *     wp --url=https://example.com/subsite/ typesense network prepare
     *     wp --url=https://example.com/subsite/ typesense network index --yes
     *     wp --url=https://example.com/subsite/ typesense network activate --yes
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $provisioner = new SiteProvisioner();
        try {
            switch ($args[0] ?? '') {
                case 'prepare':
                    $provisioner->prepare();
                    break;
                case 'index':
                    $provisioner->index(fn () => (new Actions\IndexAction())->handle([], $assocArgs));
                    break;
                case 'activate':
                    \WP_CLI::confirm('Have you reviewed the candidate content? Activate it for this site?', $assocArgs);
                    $provisioner->activate();
                    break;
                default:
                    \WP_CLI::error('Use prepare, index, or activate.');
            }
            \WP_CLI::success('Network collection operation completed.');
        } catch (\Throwable $e) {
            \WP_CLI::error(get_class($e) === \RuntimeException::class ? $e->getMessage() : 'Typesense operation failed. Check the network connection and retry.');
        }
    }
}
