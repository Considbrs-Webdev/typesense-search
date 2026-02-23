<template data-pagination-tmpl="prev">
    <wa-button size="small" class="ts-pagination__btn-prev">
        <wa-icon name="chevron-left" label="Previous"></wa-icon>
    </wa-button>
</template>

<template data-pagination-tmpl="next">
    <wa-button size="small" class="ts-pagination__btn-next">
        <wa-icon name="chevron-right" label="Next"></wa-icon>
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
