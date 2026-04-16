<template data-pagination-tmpl="prev">
    <wa-button size="small" class="ts-pagination__btn-prev" type="button">
        <wa-icon slot="start" name="chevron-left" aria-hidden="true"></wa-icon>
        <span class="ts-sr-only">{{ $lang->paginationPrevious }}</span>
    </wa-button>
</template>

<template data-pagination-tmpl="next">
    <wa-button size="small" class="ts-pagination__btn-next" type="button">
        <wa-icon slot="start" name="chevron-right" aria-hidden="true"></wa-icon>
        <span class="ts-sr-only">{{ $lang->paginationNext }}</span>
    </wa-button>
</template>

<template data-pagination-tmpl="page">
    <wa-button size="small" appearance="outlined" class="ts-pagination__page"></wa-button>
</template>

<template data-pagination-tmpl="page-active">
    <wa-button size="small" appearance="filled" class="ts-pagination__page ts-pagination__page--active"
        disabled></wa-button>
</template>

<template data-pagination-tmpl="ellipsis">
    <span class="ts-pagination__ellipsis">…</span>
</template>

<template data-pagination-tmpl="compact-label">
    <span class="ts-pagination__page-label"></span>
</template>
