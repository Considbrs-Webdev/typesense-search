<?php

namespace TypesenseSearch;

use TypesenseSearch\Bootstrap\AdminFeature;
use TypesenseSearch\Bootstrap\CliFeature;
use TypesenseSearch\Bootstrap\FrontendFeature;
use TypesenseSearch\Bootstrap\IndexingFeature;
use TypesenseSearch\Bootstrap\PinnedResultsFeature;
use TypesenseSearch\Bootstrap\SearchStatisticsFeature;
use TypesenseSearch\Indexing\IndexingRegistry;
use TypesenseSearch\Logger\ErrorLogLogger;
use TypesenseSearch\Logger\IndexingLogLogger;
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

        // Load constant overrides before anything reads options.
        new ConstantsLoader();

        // Build shared services once so every component uses the same instances.
        $settings      = new SettingsRepository();
        $clientService = new TypesenseClientService($settings);
        $logger        = new IndexingLogLogger(new ErrorLogLogger());

        // Load translations early so they're available in all components.
        add_action('init', fn() => load_plugin_textdomain(
            'typesense-search',
            false,
            './typesense-search/languages'
        ));

        new Templates();

        new ACF\Fields();

        (new AdminFeature($settings))->register();

        (new FrontendFeature($settings))->register();

        $statsFeature = new SearchStatisticsFeature($settings);
        $statsFeature->register();

        (new PinnedResultsFeature($settings))->register();

        $indexingFeature = new IndexingFeature($settings, $clientService, $logger);
        $indexingFeature->register();
        self::$registry = $indexingFeature->getRegistry();

        (new CliFeature($settings, $statsFeature->getRepository()))->register();
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
