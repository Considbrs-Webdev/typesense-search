<?php
/**
 * Synonyms admin app shell.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap ts-synonyms">
    <div id="ts-synonyms-app" class="ts-synonyms__app">
        <div class="ts-synonyms__loading" aria-live="polite">
            <?php esc_html_e('Loading synonyms...', 'typesense-search'); ?>
        </div>
    </div>
</div>
