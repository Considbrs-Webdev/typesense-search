<?php
/**
 * Pinned results admin app shell.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap ts-pinned-results">
    <h1><?php esc_html_e('Pinned search results', 'typesense-search'); ?></h1>
    <div id="ts-pinned-results-app" class="ts-pinned-results__app">
        <div class="ts-pinned-results__loading">
            <?php esc_html_e('Loading pinned results...', 'typesense-search'); ?>
        </div>
    </div>
</div>
