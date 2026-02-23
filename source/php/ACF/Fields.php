<?php

namespace TypesenseSearch\ACF;

class Fields {
    public function __construct() {
        add_action('acf/init', array($this, 'acfAutoImportExport'));
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
}
