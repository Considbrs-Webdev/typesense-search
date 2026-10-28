<?php
/**
 * Hit card template (default). Placeholders in {BRACES} are filled by the search script.
 */

if (!defined('WPINC')) {
    die;
}
?>
<template data-js-search-hit-template-default>
    <article class="ts-search-hit-card ts-plain-card">
        <div class="ts-plain-card__meta">
            <span class="ts-plain-card__badge">{SEARCH_HIT_SUBHEADING}</span>
            <time class="ts-plain-card__date">{SEARCH_HIT_DATE}</time>
        </div>
        <h2 class="ts-plain-card__heading">
            <a class="ts-search-hit-card__link" href="{SEARCH_HIT_LINK}">{SEARCH_HIT_HEADING}</a>
        </h2>
        <p class="ts-plain-card__excerpt">{SEARCH_HIT_EXCERPT}</p>
        <span class="ts-plain-card__path">{SEARCH_HIT_PATH}</span>
    </article>
</template>
