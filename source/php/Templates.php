<?php

namespace TypesenseSearch;

class Templates {
    public function __construct() {
        add_filter('Municipio/viewPaths', array($this, 'addViewPaths'), 10, 3);
    }

    public function addViewPaths($arr, $arg2 = null, $arg3 = null) {
        $arr[] = TYPESENSESEARCH_VIEW_PATH;

        return $arr;
    }
}
