<?php

declare(strict_types=1);

/**
 * Bootstrap for wp-phpunit integration tests (real WordPress + real Typesense).
 */

$_tests_dir = getenv('WP_TESTS_DIR');
if ($_tests_dir === false || $_tests_dir === '') {
    $_tests_dir = '/tmp/typesense-search-wp-tests/wordpress-tests-lib';
}

if (!is_dir($_tests_dir)) {
    fwrite(STDERR, "WP test library not found at {$_tests_dir}. Run bin/install-wp-tests.sh first.\n");
    exit(1);
}

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// WordPress 6.7+ test bootstrap expects Yoast PHPUnit Polyfills.
if (!defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH')) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname(__DIR__, 2) . '/vendor/yoast/phpunit-polyfills');
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter('muplugins_loaded', static function (): void {
    require dirname(__DIR__) . '/fixtures/modularity-stub/modularity-stub.php';
    require dirname(__DIR__, 2) . '/typesense-search.php';
}, 0);

require $_tests_dir . '/includes/bootstrap.php';
