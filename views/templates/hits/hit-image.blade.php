@element([
    'componentElement' => 'template',
    'attributeList' => [
        'data-js-search-hit-template-image' => true
    ]
])
    @card([
        'heading' => '{SEARCH_HIT_HEADING}',
        'meta' => '{SEARCH_HIT_SUBHEADING}',
        'content' => '{SEARCH_HIT_EXCERPT}',
        'image' => [
            'src' => '{SEARCH_HIT_IMAGE_URL}',
            'alt' => '{SEARCH_HIT_IMAGE_ALT}'
        ],
        'link' => '{SEARCH_HIT_LINK}',
        'classList' => ['c-card--size-md'],
        'attributeList' => ['aria-label' => '{SEARCH_HIT_ARIA_LABEL}']
    ])
    @endcard
@endelement
