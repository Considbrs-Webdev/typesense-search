<?php
/**
 * External pages admin app shell.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap ts-external-pages">
    <div id="ts-external-pages-app" class="ts-external-pages__app">
        <div class="ts-external-pages__loading" aria-live="polite">
            <?php esc_html_e('Loading external pages...', 'typesense-search'); ?>
        </div>
    </div>
</div>
