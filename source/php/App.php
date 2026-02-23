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
    }
}
