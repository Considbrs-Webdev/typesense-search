<?php
/**
 * Pinned results admin app shell.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap ts-pinned-results">
    <div id="ts-pinned-results-app" class="ts-pinned-results__app">
        <div class="ts-pinned-results__loading" aria-live="polite">
            <?php esc_html_e('Loading pinned results...', 'typesense-search'); ?>
        </div>
    </div>
</div>
