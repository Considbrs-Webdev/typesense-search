@element([
    'componentElement' => 'template',
    'attributeList' => [
        'data-js-search-hit-template-image' => true
    ]
])
    <a class="c-card c-card--size-md c-card--has-image c-card--action c-card--ratio-16-9" aria-label="{SEARCH_HIT_ARIA_LABEL}"
        href="{SEARCH_HIT_LINK}" data-uid="69a08bfe862df">
        <div class="c-card__paint-container">
            <div class="c-card__image-container">
                <figure class="c-image c-card__image c-image--cover">
                    <div class="c-image__image-wrapper">
                        <img loading="lazy" class="c-image__image" src="{SEARCH_HIT_IMAGE_URL}" alt="{SEARCH_HIT_IMAGE_ALT}">
                    </div>
                </figure>
            </div>
            <div class="c-card__body">
                <div class="c-group c-group--vertical c-group--gap-1">
                    <div class="c-group c-group--horizontal c-group--align-items-center c-group--gap-1">
                        <span class="c-typography c-card__sub-heading u-margin__y--0 c-typography__variant--h6">
                            {SEARCH_HIT_SUBHEADING}
                        </span>
                        <span class="u-color__text--primary u-display--inline-flex u-align-items--center"
                            aria-hidden="true">
                            <svg width="8" height="8" viewBox="0 0 8 8" fill="currentColor" aria-hidden="true">
                                <circle cx="4" cy="4" r="4" />
                            </svg>
                        </span>
                        <time class="c-date">{SEARCH_HIT_DATE}</time>
                    </div>
                    <h2 class="c-typography c-card__heading u-margin__y--0 c-typography__variant--h3">
                        {SEARCH_HIT_HEADING}
                    </h2>
                    <p class="c-typography c-card__content c-typography__variant--p u-margin__y--0">
                        {SEARCH_HIT_EXCERPT}
                    </p>
                    <span class="c-typography c-typography__variant--meta">
                        {SEARCH_HIT_PATH}
                    </span>
                </div>
            </div>
        </div>
    </a>
@endelement
