@element([
    'componentElement' => 'template',
    'attributeList' => [
        'data-js-search-hit-template-jobposting' => true
    ]
])
    <a class="c-card c-card--size-md c-card--action c-card--ratio-16-9" aria-label="{SEARCH_HIT_ARIA_LABEL}"
        href="{SEARCH_HIT_LINK}" data-uid="69a08caed0d4a">
        <div class="c-card__paint-container">
            <div class="c-card__body">
                <!-- group.blade.php -->
                <div class="c-group c-card__heading-container c-group--horizontal c-group--justify-content-space-between c-group--align-items-center"
                    data-uid="69a08caed12a4">
                    <!-- group.blade.php -->
                    <div class="c-group c-group--vertical" data-uid="69a08caed11b3">
                        <!-- typography.blade.php   original: h2 -->
                        <h2 class="c-typography c-card__heading u-margin__y--0 c-typography__variant--h3"
                            data-uid="69a08caed0edf">
                            {SEARCH_HIT_HEADING}
                        </h2>
                        <!-- typography.blade.php   original: span -->
                        <span class="c-typography c-card__sub-heading u-margin__y--0 c-typography__variant--h6"
                            data-uid="69a08caed0fc8">
                            {SEARCH_HIT_SUBHEADING}
                        </span>
                    </div>
                </div> <!-- typography.blade.php   original: span -->
                <span class="c-typography c-card__date c-typography__variant--meta" data-uid="69a08caed1b46">
                    <!-- icon.blade.php -->
                    <span
                        class="c-icon c-icon--date-range c-icon--material c-icon--material-date_range material-symbols material-symbols-rounded material-symbols-sharp material-symbols-outlined  c-icon--size-sm"
                        data-material-symbol="date_range" role="img" data-nosnippet="1" translate="no"
                        aria-label="Ikon: Kalender" aria-hidden="false" data-uid="69a08caed177a">
                    </span>
                    <time class="c-date" data-uid="69a08caed1a3e"><strong>{{ __('Published', 'municipio') }}</strong>: {SEARCH_HIT_DATE}</time>
                </span> <!-- typography.blade.php   original: p -->
                <span class="c-typography c-card__date c-typography__variant--meta" data-uid="69a08caed1b46">
                    <!-- icon.blade.php -->
                    <span
                        class="c-icon c-icon--date-range c-icon--material c-icon--material-date_range material-symbols material-symbols-rounded material-symbols-sharp material-symbols-outlined  c-icon--size-sm"
                        data-material-symbol="date_range" role="img" data-nosnippet="1" translate="no"
                        aria-label="Ikon: Kalender" aria-hidden="false" data-uid="69a08caed177a">
                    </span>
                    <time class="c-date" data-uid="69a08caed1a3e"><strong>{{ __('Valid through', 'municipio') }}</strong>: {SEARCH_HIT_VALID_THROUGH}</time>
                </span> <!-- typography.blade.php   original: p -->
                <p class="c-typography c-card__content c-typography__variant--p" data-uid="69a08caed1c53">
                    {SEARCH_HIT_EXCERPT}
                </p>
            </div>

        </div>
    </a>
@endelement
