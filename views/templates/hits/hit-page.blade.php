@element([
    'componentElement' => 'template',
    'attributeList' => [
        'data-js-search-hit-template-page' => true
    ]
])
    <a class="c-card c-card--size-md c-card--has-image c-card--action c-card--ratio-16-9" aria-label="{SEARCH_HIT_ARIA_LABEL}"
        href="{SEARCH_HIT_LINK}" data-uid="69a08bfe862df">
        <div class="c-card__paint-container">
            <div class="c-card__image-container">
                <!-- image.blade.php -->

                <figure class="c-image c-card__image c-image--cover" data-uid="69a08bfe86453">
                    <div class="c-image__image-wrapper">
                        <img loading="lazy" class="c-image__image" src="{SEARCH_HIT_IMAGE_URL}" alt="{SEARCH_HIT_IMAGE_ALT}">
                    </div>
                </figure>

            </div>
            <div class="c-card__body">
                <!-- group.blade.php -->
                <div class="c-group c-card__heading-container c-group--horizontal c-group--justify-content-space-between c-group--align-items-center"
                    data-uid="69a08bfe869d4">
                    <!-- group.blade.php -->
                    <div class="c-group c-group--vertical" data-uid="69a08bfe868e1">
                        <!-- typography.blade.php   original: h2 -->
                        <h2 class="c-typography c-card__heading u-margin__y--0 c-typography__variant--h3"
                            data-uid="69a08bfe8660e">
                            {SEARCH_HIT_HEADING}
                        </h2>
                        <!-- typography.blade.php   original: span -->
                        <span class="c-typography c-card__sub-heading u-margin__y--0 c-typography__variant--h6"
                            data-uid="69a08bfe866f9">
                            {SEARCH_HIT_SUBHEADING}
                        </span>
                        <!-- typography.blade.php   original: span -->
                        <span class="c-typography c-typography__variant--meta" data-uid="69a08bfe867fb">
                            {SEARCH_HIT_PATH}
                        </span>
                    </div>
                </div> <!-- typography.blade.php   original: span -->
                <span class="c-typography c-card__date c-typography__variant--meta" data-uid="69a08bfe874e4">
                    <!-- icon.blade.php -->
                    <span
                        class="c-icon c-icon--date-range c-icon--material c-icon--material-date_range material-symbols material-symbols-rounded material-symbols-sharp material-symbols-outlined  c-icon--size-sm"
                        data-material-symbol="date_range" role="img" data-nosnippet="1" translate="no"
                        aria-label="Ikon: Kalender" aria-hidden="false" data-uid="69a08bfe86fc8">
                    </span>
                    <time class="c-date" data-uid="69a08bfe873d6">{SEARCH_HIT_DATE}</time>



                    <!-- Date component: Invalid date -->
                </span> <!-- typography.blade.php   original: p -->
                <p class="c-typography c-card__content c-typography__variant--p" data-uid="69a08bfe875f1">
                    {SEARCH_HIT_EXCERPT}
                </p>
            </div>

        </div>
    </a>
@endelement
