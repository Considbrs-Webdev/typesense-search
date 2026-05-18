<?php

namespace TypesenseSearch;

use TypesenseSearch\Indexing\IndexingRegistry;
use TypesenseSearch\Indexing\Strategies\PdfIndexingStrategy;
use TypesenseSearch\Indexing\Strategies\PostIndexingStrategy;
use TypesenseSearch\Logger\ErrorLogLogger;
use TypesenseSearch\Logger\LoggerInterface;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Services\TypesenseClientService;

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

        // Build shared services once so every component uses the same instances.
        $settings      = new SettingsRepository();
        $clientService = new TypesenseClientService($settings);
        $logger        = new ErrorLogLogger();

        // Load translations early so they're available in all components.
        add_action('init', fn() => load_plugin_textdomain(
            'typesense-search',
            false,
            './typesense-search/languages'
        ));

        new Templates();

        new ACF\Fields();

        // Admin UI components
        new Admin\Settings();
        new Admin\SettingsAjax();
        new Admin\MetaBox();

        // Frontend components
        new Frontend\Assets();
        new Frontend\EnrichSearchTemplate();
        new Frontend\TypesenseConfig($settings);

        // ── Indexing: build registry with strategies ────────────────────────
        // Register more specific strategies first — the registry evaluates
        // them in order and the first match wins.
        self::$registry = new IndexingRegistry();
        self::$registry->register(new PdfIndexingStrategy($clientService, $settings, $logger));
        self::$registry->register(new PostIndexingStrategy($clientService, $settings, $logger));

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
        do_action('Municipio/TypesenseSearch/RegisterStrategies', self::$registry, $clientService, $settings, $logger);

        new Indexing\IndexingHooks(self::$registry);

        // ── Document enrichers ──────────────────────────────────────────────
        // These hook into DocumentBuilder's filter chain to add fields to
        // specific post types. External code can do the same via:
        //   add_filter(DocumentBuilder::FILTER_BUILD, ...)
        //   add_filter(sprintf(DocumentBuilder::FILTER_BUILD_POST_TYPE, 'my_type'), ...)
        new Indexing\Enrichers\JobPostingEnricher();
        new Indexing\Enrichers\ModularityEnricher($settings);
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
