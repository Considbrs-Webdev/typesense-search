<?php

/**
 * Plugin Name:       Typesense Search
 * Plugin URI:        https://github.com/considbrs-webdev/typesense-search
 * Description:       A Typesense Search Plugin for WordPress and Municipio.
 * Version: 1.2.0
 * Author:            Consid Borås AB
 * Author URI:        https://github.com/considbrs-webdev
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       typesense-search
 * Domain Path:       /languages
 */

// Protect against direct file access
if (! defined('WPINC')) {
    die;
}

define('TYPESENSESEARCH_PATH', plugin_dir_path(__FILE__));
define('TYPESENSESEARCH_URL', plugins_url('', __FILE__));
define('TYPESENSESEARCH_VIEW_PATH', plugin_dir_path(__FILE__) . 'views');


// Autoload from plugin
if (file_exists(TYPESENSESEARCH_PATH . 'vendor/autoload.php')) {
    require_once TYPESENSESEARCH_PATH . 'vendor/autoload.php';
}

register_activation_hook(__FILE__, [\TypesenseSearch\SearchStatistics\Retention::class, 'activate']);
register_deactivation_hook(__FILE__, [\TypesenseSearch\SearchStatistics\Retention::class, 'deactivate']);

// Start application
new TypesenseSearch\App();
