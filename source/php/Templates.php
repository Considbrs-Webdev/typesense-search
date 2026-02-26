<?php

namespace TypesenseSearch;

use TypesenseSearch\Typesense\ClientFactory;

class Templates {
    public function __construct() {
        add_filter('Municipio/viewPaths', array($this, 'addViewPaths'), 10, 1);
    }

    public function addViewPaths($arr) {
        if (is_admin() || !isset($_GET['s']) || !ClientFactory::isReadyWithCollection()) {
            return $arr;
        }

        $arr[] = TYPESENSESEARCH_VIEW_PATH;

        return $arr;
    }
}
