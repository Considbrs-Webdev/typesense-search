<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/tests/fixtures/wordpress/');
}

if (!class_exists('WP_Post')) {
    class WP_Post
    {
        public function __construct(
            public int $ID = 0,
            public string $post_title = '',
            public string $post_content = '',
            public string $post_excerpt = '',
            public string $post_type = 'post',
            public string $post_date_gmt = '',
            public string $post_modified_gmt = '',
            public int $post_author = 0
        ) {
        }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(
            public string $code = '',
            public string $message = '',
            public mixed $data = null
        ) {
        }
    }
}
