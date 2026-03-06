<?php

namespace TypesenseSearch;

use TypesenseSearch\Indexing\IndexingRegistry;
use TypesenseSearch\Indexing\Strategies\PdfIndexingStrategy;
use TypesenseSearch\Indexing\Strategies\PostIndexingStrategy;

/**
 * Class App
 * 
 * Main application bootstrap class.
 * Initialize your plugin components here.
 * 
 * @package TypesenseSearch
 */
class App {
    /**
     * The central indexing registry, available for other components (e.g. CLI)
     * that need to resolve strategies.
     */
    private static IndexingRegistry $registry;

    public function __construct() {

        // Load .env overrides before anything reads options.
        new EnvLoader();

        new Config();
        new Templates();

        new ACF\Fields();

        new Admin\Settings();
        new Admin\SettingsAjax();
        new Admin\MetaBox();

        new Frontend\Assets();
        new Frontend\EnrichSearchTemplate();
        new Frontend\TypesenseConfig();

        // ── Indexing: build registry with strategies ────────────────────────
        // Register more specific strategies first — the registry evaluates
        // them in order and the first match wins.
        self::$registry = new IndexingRegistry();
        self::$registry->register(new PdfIndexingStrategy());
        self::$registry->register(new PostIndexingStrategy());

        /**
         * Fires after the built-in indexing strategies are registered,
         * allowing external plugins and themes to add their own strategies
         * without modifying the core plugin.
         *
         * @param IndexingRegistry $registry The shared strategy registry.
         *
         * Example:
         *   add_action('Municipio/TypesenseSearch/RegisterStrategies',
         *       function (IndexingRegistry $registry): void {
         *           $registry->register(new MyCustomProductStrategy());
         *       });
         */
        do_action('Municipio/TypesenseSearch/RegisterStrategies', self::$registry);

        new Indexing\IndexingHooks(self::$registry);

        // ── Document enrichers ──────────────────────────────────────────────
        // These hook into DocumentBuilder's filter chain to add fields to
        // specific post types. External code can do the same via:
        //   add_filter(DocumentBuilder::FILTER_BUILD, ...)
        //   add_filter(sprintf(DocumentBuilder::FILTER_BUILD_POST_TYPE, 'my_type'), ...)
        new Indexing\Enrichers\JobPostingEnricher();
        new Indexing\Enrichers\ModularityEnricher();
        new Indexing\Enrichers\PageEnricher();

        if (defined('WP_CLI') && constant('WP_CLI') === true) {
            \WP_CLI::add_command('typesense', CLI\IndexCommand::class);
        }
    }

    /**
     * Retrieve the global IndexingRegistry instance.
     *
     * CLI commands and external code that need to resolve or call strategies
     * directly should use this accessor rather than instantiating the registry
     * themselves.
     *
     * @return IndexingRegistry
     */
    public static function getRegistry(): IndexingRegistry
    {
        return self::$registry;
    }
}
