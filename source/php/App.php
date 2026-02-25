<?php

namespace TypesenseSearch;

/**
 * Class App
 * 
 * Main application bootstrap class.
 * Initialize your plugin components here.
 * 
 * @package TypesenseSearch
 */
class App {
    public function __construct() {

        new Config();
        new Templates();

        new ACF\Fields();

        new Admin\Settings();
        new Admin\SettingsAjax();
        new Admin\MetaBox();

        new Frontend\Assets();
        new Frontend\EnrichSearchTemplate();
        new Frontend\TypesenseConfig();

        new Indexing\IndexingHooks();
        new Indexing\Adapters\PageAdapter();
        new Indexing\Adapters\ModularityAdapter();

        if (defined('WP_CLI') && constant('WP_CLI') === true) {
            \WP_CLI::add_command('typesense', CLI\IndexCommand::class);
        }
    }
}
