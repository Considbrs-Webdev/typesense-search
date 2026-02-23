<template data-pagination-tmpl="prev">
    <wa-button variant="outlined" size="small" class="ts-pagination__btn-prev" data-page="">
        <wa-icon name="chevron-left" label="Previous"></wa-icon>
    </wa-button>
</template>

<template data-pagination-tmpl="next">
    <wa-button variant="outlined" size="small" class="ts-pagination__btn-next" data-page="">
        <wa-icon name="chevron-right" label="Next"></wa-icon>
    </wa-button>
</template>

<template data-pagination-tmpl="page">
    <wa-button size="small" variant="outlined" class="ts-pagination__page" data-page=""></wa-button>
</template>

<template data-pagination-tmpl="page-active">
    <wa-button size="small" variant="filled" class="ts-pagination__page ts-pagination__page--active" disabled
        data-page=""></wa-button>
</template>

<template data-pagination-tmpl="ellipsis">
    <span class="ts-pagination__ellipsis">…</span>
</template>

<template data-pagination-tmpl="compact-label">
    <span class="ts-pagination__page-label"></span>
</template>
