<?php

namespace TypesenseSearch;

class Templates {
    public function __construct() {
        add_filter('Municipio/viewPaths', array($this, 'addViewPaths'), 10, 1);
    }

    public function addViewPaths($arr) {
        $arr[] = TYPESENSESEARCH_VIEW_PATH;

        return $arr;
    }
}
