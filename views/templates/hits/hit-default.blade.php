@element([
    'componentElement' => 'template',
    'attributeList' => [
        'data-js-search-hit-template-default' => true
    ]
])
    <div class="c-card c-card--size-md c-card--action ts-search-hit-card" data-uid="69a08caed0d4a">
        <div class="c-card__paint-container">
            <div class="c-card__body">
                <div class="c-group c-group--vertical c-group--gap-1">
                    <div class="c-group c-group--horizontal c-group--align-items-center c-group--gap-1">
                        <span class="c-badge c-badge--primary">{SEARCH_HIT_SUBHEADING}</span>
                        <time class="c-date">{SEARCH_HIT_DATE}</time>
                    </div>
                    <h2 class="c-typography c-card__heading u-margin__y--0 c-typography__variant--h3">
                        <a class="ts-search-hit-card__link" href="{SEARCH_HIT_LINK}">{SEARCH_HIT_HEADING}</a>
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
    </div>
@endelement
