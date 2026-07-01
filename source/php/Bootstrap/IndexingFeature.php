<?php

namespace TypesenseSearch\Bootstrap;

use TypesenseSearch\Indexing\DisabledContentPruner;
use TypesenseSearch\Indexing\Enrichers\JobPostingEnricher;
use TypesenseSearch\Indexing\Enrichers\ModularityEnricher;
use TypesenseSearch\Indexing\Enrichers\PageEnricher;
use TypesenseSearch\Indexing\IndexingHooks;
use TypesenseSearch\Indexing\IndexingRegistry;
use TypesenseSearch\Indexing\Strategies\PdfIndexingStrategy;
use TypesenseSearch\Indexing\Strategies\PostIndexingStrategy;
use TypesenseSearch\Logger\LoggerInterface;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Services\TypesenseClientService;

/**
 * Wires the indexing pipeline: strategy registry, document enrichers, and
 * the WordPress hooks that trigger indexing on post save/delete.
 *
 * Fires the 'Municipio/TypesenseSearch/RegisterStrategies' action so external
 * code can register additional strategies without modifying core.
 *
 * @package TypesenseSearch\Bootstrap
 */
class IndexingFeature
{
    private IndexingRegistry $registry;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly TypesenseClientService $clientService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function register(): void
    {
        // Build registry — register more specific strategies first; the registry
        // evaluates them in order and the first match wins.
        $this->registry = new IndexingRegistry();
        $this->registry->register(new PdfIndexingStrategy($this->clientService, $this->settings, $this->logger));
        $this->registry->register(new PostIndexingStrategy($this->clientService, $this->settings, $this->logger));

        /**
         * Fires after the built-in indexing strategies are registered,
         * allowing external plugins and themes to add their own strategies
         * without modifying the core plugin.
         *
         * @param IndexingRegistry $registry The shared strategy registry.
         *
         * Example:
         *   add_action('Municipio/TypesenseSearch/RegisterStrategies',
         *       function (IndexingRegistry $registry, TypesenseClientService $clientService, SettingsRepository $settings, LoggerInterface $logger): void {
         *           $registry->register(new MyCustomProductStrategy($clientService, $settings, $logger));
         *       }, 10, 4);
         */
        do_action('Municipio/TypesenseSearch/RegisterStrategies', $this->registry, $this->clientService, $this->settings, $this->logger);

        new IndexingHooks($this->registry);
        (new DisabledContentPruner($this->clientService, $this->settings, $this->logger))->register();

        // Document enrichers hook into DocumentBuilder's filter chain to add
        // fields to specific post types.
        new JobPostingEnricher();
        new ModularityEnricher($this->settings);
        new PageEnricher();
    }

    /**
     * Retrieve the shared IndexingRegistry instance.
     *
     * Available after register() has been called.
     *
     * @return IndexingRegistry
     */
    public function getRegistry(): IndexingRegistry
    {
        return $this->registry;
    }
}
