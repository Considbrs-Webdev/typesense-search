<?php

namespace TypesenseSearch\Indexing\Adapters;

use TypesenseSearch\Indexing\DocumentBuilder;

use Municipio\SchemaData\Utils\SchemaToPostTypesResolver\SchemaToPostTypeResolver;

class JobPostingAdapter {
    public function __construct() 
    {
        add_action('wp_loaded', function() {
            $wpService = \Modularity\Helper\WpService::get();
            $acfService = \Modularity\Helper\AcfService::get();

            $schemaToPostTypeResolver = new SchemaToPostTypeResolver($acfService, $wpService);
            $postTypes = $schemaToPostTypeResolver->resolve('JobPosting');

            foreach ($postTypes as $postType) {
                add_filter(
                    sprintf(DocumentBuilder::FILTER_BUILD_POST_TYPE, $postType),
                    [$this, 'addLastApplicationDate'],
                    10,
                    2
                );
            }
        });
    }

    public function addLastApplicationDate(array $document, \WP_Post $post): array
    {
        $schemaData = get_post_meta($post->ID, 'schemaData', true);

        // $document['post_date_formatted'] = date_i18n('l j F, Y', $document['date']);

        if (is_array($schemaData) && isset($schemaData['validThrough'])) {
            $document['last_application_date'] = date_i18n('l j F, Y', strtotime($schemaData['validThrough']));
        }

        return $document;
    }  
}