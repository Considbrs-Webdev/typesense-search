<?php

namespace TypesenseSearch\Indexing\Adapters;

use TypesenseSearch\Indexing\DocumentBuilder;

/**
 * Class ModularityAdapter
 *
 * Appends the rendered text content of Modularity modules to the `content`
 * field of the indexed document. This ensures that text inside modules is
 * discoverable through full-text search.
 *
 * Only active when the Modularity plugin is present. Modules that are hidden
 * or belong to disabled areas / post-type configurations are skipped, matching
 * the same logic used when rendering the front-end page.
 *
 * Hook: Municipio/TypesenseSearch/DocumentBuilder/build
 *
 * @package TypesenseSearch\Indexing\Adapters
 */
class ModularityAdapter
{
    public function __construct()
    {
        if (!$this->isInstalled()) {
            return;
        }

        add_filter(DocumentBuilder::FILTER_BUILD, [$this, 'appendModularityContent'], 10, 2);
    }

    /**
     * Renders all eligible Modularity modules for the post and appends their
     * stripped text content to the existing `content` field.
     *
     * @param array<string, mixed> $document The document being built.
     * @param \WP_Post             $post     The source post object.
     * @return array<string, mixed>
     */
    public function appendModularityContent(array $document, \WP_Post $post): array
    {
        try {
            $modules = \Modularity\Editor::getPostModules($post->ID);

            if (empty($modules)) {
                return $document;
            }

            $modularityOptions = get_option('modularity-options', []);
            $enabled_modules   = $modularityOptions['enabled-modules'] ?? [];

            // getPostTemplate reads the global $post, so temporarily override it.
            $original_post       = $GLOBALS['post'] ?? null;
            $GLOBALS['post']     = $post;
            $template_key        = \Modularity\Helper\Post::getPostTemplate($post->ID);
            if ($original_post !== null) {
                $GLOBALS['post'] = $original_post;
            } else {
                unset($GLOBALS['post']);
            }

            $enabled_areas = $modularityOptions['enabled-areas'][$template_key] ?? [];

            $modules_content = '';

            foreach ($modules as $area_slug => $area) {
                // Skip 'main-content' — the post body is already in $document['content'].
                if ($area_slug === 'main-content') {
                    continue;
                }

                if (!in_array($area_slug, $enabled_areas)) {
                    continue;
                }

                if (!isset($area['modules']) || !is_array($area['modules'])) {
                    continue;
                }

                foreach ($area['modules'] as $module) {
                    if (isset($module->hidden) && $module->hidden === true) {
                        continue;
                    }

                    if (!in_array($module->post_type, $enabled_modules)) {
                        continue;
                    }

                    $module_html = \Modularity\App::$display->outputModule(
                        $module,
                        ['edit_module' => true],
                        [],
                        false
                    );

                    if (!empty($module_html)) {
                        $modules_content .= ' ' . wp_strip_all_tags($module_html);
                    }
                }
            }

            if (!empty($modules_content)) {
                $document['content'] = trim($document['content'] . ' ' . trim($modules_content));
            }
        } catch (\Exception $e) {
        }

        return $document;
    }

    /**
     * Returns true when both Modularity classes required for rendering are available.
     *
     * @return bool
     */
    private function isInstalled(): bool
    {
        return class_exists('\\Modularity\\Editor') && class_exists('\\Modularity\\App');
    }
}
