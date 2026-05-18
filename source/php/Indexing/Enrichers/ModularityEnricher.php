<?php

namespace TypesenseSearch\Indexing\Enrichers;

use TypesenseSearch\Indexing\DocumentBuilder;
use TypesenseSearch\Services\SettingsRepository;

/**
 * Class ModularityEnricher
 *
 * Appends the rendered text content of Modularity modules to the `content`
 * field of the indexed document. This ensures that text inside modules is
 * discoverable through full-text search.
 *
 * Only active when the Modularity plugin is present and the "Index Modularity
 * content" setting is enabled. Modules that are hidden or belong to disabled
 * areas / post-type configurations are skipped, matching the same logic used
 * when rendering the front-end page.
 *
 * Additionally, only post types that have Modularity enabled (via the
 * `enabled-post-types` key in the `modularity-options` option) will have
 * module content appended. This list is cached once at construction time to
 * avoid repeated database reads during bulk indexing runs.
 *
 * Hook: Municipio/TypesenseSearch/DocumentBuilder/build
 *
 * @package TypesenseSearch\Indexing\Enrichers
 */
class ModularityEnricher
{
    /**
     * Post-type slugs for which Modularity is enabled, cached at construction
     * time from the `modularity-options` WordPress option.
     *
     * @var string[]
     */
    private array $enabledPostTypes = [];

    public function __construct(private SettingsRepository $settings)
    {
        if (!$this->isInstalled()) {
            return;
        }

        if (!$this->settings->isIndexModularityEnabled()) {
            return;
        }

        $modularityOptions      = get_option('modularity-options', []);
        $this->enabledPostTypes = (array) ($modularityOptions['enabled-post-types'] ?? []);

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
        if (!empty($this->enabledPostTypes) && !in_array($post->post_type, $this->enabledPostTypes, true)) {
            return $document;
        }

        try {
            $modules = \Modularity\Editor::getPostModules($post->ID);

            if (empty($modules)) {
                return $document;
            }

            $modularityOptions = get_option('modularity-options', []);
            $enabled_modules   = $modularityOptions['enabled-modules'] ?? [];

            // getPostTemplate reads the global $post, so temporarily override it.
            $original_post   = $GLOBALS['post'] ?? null;
            $GLOBALS['post'] = $post;
            $template_key    = \Modularity\Helper\Post::getPostTemplate($post->ID);
            if ($original_post !== null) {
                $GLOBALS['post'] = $original_post;
            } else {
                unset($GLOBALS['post']);
            }

            $enabled_areas   = $modularityOptions['enabled-areas'][$template_key] ?? [];
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
                        $modules_content .= ' ' . $this->extractText($module_html);
                    }
                }
            }

            if (!empty($modules_content)) {
                $document['content'] = trim($document['content'] . ' ' . trim($modules_content));
            }
        } catch (\Exception $e) {
            // Gracefully degrade — indexing must not break when Modularity
            // encounters an unexpected state.
        }

        return $document;
    }

    /**
     * Extract plain text from an HTML string without using strip_tags().
     *
     * PHP's strip_tags() implements a quote-state machine: an unmatched `"`
     * inside a tag attribute (e.g. data-component="foo">) causes it to treat
     * the following `>` as still inside a quoted string, silently swallowing
     * all text until the next unquoted `>`. Modularity's rendered HTML
     * contains such malformed attributes.
     *
     * The regex `/<[^>]*>/` has no quote-state tracking — it simply matches
     * from `<` to the first `>`, so it handles those malformed tags correctly
     * and is substantially faster than a full DOMDocument parse.
     */
    private function extractText(string $html): string
    {
        // Remove script/style blocks (same as wp_strip_all_tags).
        $html = (string) preg_replace('@<(script|style)[^>]*?>.*?</\1>@si', '', $html);
        // Strip all remaining tags without quote-state tracking.
        return trim((string) preg_replace('/<[^>]*>/', ' ', $html));
    }

    /**
     * Returns true when both Modularity classes required for rendering are available.
     */
    private function isInstalled(): bool
    {
        return class_exists('\\Modularity\\Editor') && class_exists('\\Modularity\\App');
    }
}
