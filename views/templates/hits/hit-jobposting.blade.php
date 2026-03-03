@element([
    'componentElement' => 'template',
    'attributeList' => [
        'data-js-search-hit-template-jobposting' => true
    ]
])
    <a class="c-card c-card--size-md c-card--action" aria-label="{SEARCH_HIT_ARIA_LABEL}" href="{SEARCH_HIT_LINK}"
        data-uid="69a08caed0d4a">
        <div class="c-card__paint-container">
            <div class="c-card__body">
                <div class="c-group c-group--vertical c-group--gap-1">
                    {{-- Metadata row: type, separator dot, published date --}}
                    <div class="c-group c-group--horizontal c-group--align-items-center c-group--gap-1">
                        <span class="c-typography c-card__sub-heading u-margin__y--0 c-typography__variant--h6">
                            {SEARCH_HIT_SUBHEADING}
                        </span>
                        <span class="u-color__text--primary u-display--inline-flex u-align-items--center" aria-hidden="true">
                            <svg width="8" height="8" viewBox="0 0 8 8" fill="currentColor" aria-hidden="true">
                                <circle cx="4" cy="4" r="4" />
                            </svg>
                        </span>
                        <span class="c-typography c-typography__variant--meta">
                            <time class="c-date">{SEARCH_HIT_DATE}</time>
                        </span>
                    </div>
                    <h2 class="c-typography c-card__heading u-margin__y--0 c-typography__variant--h3">
                        {SEARCH_HIT_HEADING}
                    </h2>
                    <span class="c-typography c-typography__variant--meta u-margin__y--0">
                        <strong>{{ __('Valid through', 'municipio') }}</strong>: <time
                            class="c-date">{SEARCH_HIT_VALID_THROUGH}</time>
                    </span>
                    <p class="c-typography c-card__content c-typography__variant--p u-margin__y--0">
                        {SEARCH_HIT_EXCERPT}
                    </p>
                </div>
            </div>
        </div>
    </a>
@endelement
