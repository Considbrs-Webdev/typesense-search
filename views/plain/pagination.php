<?php
/**
 * Pagination templates cloned by the search script.
 *
 * @var object $lang
 */

if (!defined('WPINC')) {
    die;
}
?>
<template data-pagination-tmpl="prev">
    <wa-button size="small" class="ts-pagination__btn-prev" type="button">
        <wa-icon name="chevron-left" label="<?php echo esc_attr($lang->paginationPrevious); ?>"></wa-icon>
    </wa-button>
</template>

<template data-pagination-tmpl="next">
    <wa-button size="small" class="ts-pagination__btn-next" type="button">
        <wa-icon name="chevron-right" label="<?php echo esc_attr($lang->paginationNext); ?>"></wa-icon>
    </wa-button>
</template>

<template data-pagination-tmpl="page">
    <wa-button size="small" appearance="outlined" class="ts-pagination__page"></wa-button>
</template>

<template data-pagination-tmpl="page-active">
    <wa-button size="small" appearance="filled" class="ts-pagination__page ts-pagination__page--active" disabled></wa-button>
</template>

<template data-pagination-tmpl="ellipsis">
    <span class="ts-pagination__ellipsis">…</span>
</template>

<template data-pagination-tmpl="compact-label">
    <span class="ts-pagination__page-label"></span>
</template>
