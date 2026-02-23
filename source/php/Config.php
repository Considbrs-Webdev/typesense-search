<?php

namespace TypesenseSearch;

class Config {
    public function __construct() {
        add_action('init', array($this, 'loadTextdomain'));
    }

    public function loadTextdomain() {
        load_plugin_textdomain('typesense-search', false, './typesense-search/languages');
    }
}
