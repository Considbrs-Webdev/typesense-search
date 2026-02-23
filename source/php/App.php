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
        // Load text domain
        add_action('init', array($this, 'loadTextdomain'));

        // ACF auto import and export
        add_action('acf/init', array($this, 'acfAutoImportExport'));

        // View paths for templates
        add_filter('Municipio/viewPaths', array($this, 'addViewPaths'), 10, 3);
    }

    public function loadTextdomain() {
        load_plugin_textdomain('typesense-search', false, plugin_basename(dirname(__FILE__)) . '/languages');
    }

    public function acfAutoImportExport() {
        $acfExportManager = new \AcfExportManager\AcfExportManager();
        $acfExportManager->setTextdomain('typesense-search');
        $acfExportManager->setExportFolder(TYPESENSESEARCH_PATH . '/source/php/AcfFields/');
        $acfExportManager->autoExport(array(
            //'general-settings'  => 'group_694a9d25909d8',
        ));
        $acfExportManager->import();
    }

    public function addViewPaths($arr, $arg2 = null, $arg3 = null) {
        $arr[] = TYPESENSESEARCH_VIEW_PATH;

        return $arr;
    }
}
