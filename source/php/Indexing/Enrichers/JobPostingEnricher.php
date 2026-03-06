<?php

namespace TypesenseSearch\Indexing\Enrichers;

use TypesenseSearch\Indexing\DocumentBuilder;

use Municipio\SchemaData\Utils\SchemaToPostTypesResolver\SchemaToPostTypeResolver;

/**
 * Class JobPostingEnricher
 *
 * Appends a `last_application_date` field to documents for post types that are
 * mapped to the JobPosting schema. The date is derived from the `validThrough`
 * property stored in the post's `schemaData` meta.
 *
 * Hooks into the dynamic post-type filter provided by DocumentBuilder so no
 * new indexing strategy is needed — just a lightweight document enrichment.
 *
 * Hook pattern: Municipio/TypesenseSearch/DocumentBuilder/{post_type}/build
 *
 * @package TypesenseSearch\Indexing\Enrichers
 */
class JobPostingEnricher
{
    public function __construct()
    {
        add_action('wp_loaded', function () {
            $wpService  = \Modularity\Helper\WpService::get();
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

    /**
     * Appends the last application date to the document.
     *
     * @param array<string, mixed> $document
     * @param \WP_Post             $post
     * @return array<string, mixed>
     */
    public function addLastApplicationDate(array $document, \WP_Post $post): array
    {
        $schemaData = get_post_meta($post->ID, 'schemaData', true);

        if (is_array($schemaData) && isset($schemaData['validThrough'])) {
            $document['last_application_date'] = date_i18n('l j F, Y', strtotime($schemaData['validThrough']));
        }

        return $document;
    }
}
