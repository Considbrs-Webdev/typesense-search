<?php
/**
 * Page wrapper for the plain (non-Municipio) search page.
 *
 * Override by copying to `typesense-search/template.php` in your theme.
 */

use TypesenseSearch\Frontend\PlainSearchTemplate;

if (!defined('WPINC')) {
    die;
}

$data = PlainSearchTemplate::getViewData();

get_header();
?>
<main id="primary" class="ts-plain">
    <?php PlainSearchTemplate::render('search.php', $data); ?>
</main>
<?php
get_footer();
