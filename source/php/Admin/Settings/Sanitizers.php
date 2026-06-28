<?php

namespace TypesenseSearch\Admin\Settings;

use TypesenseSearch\Typesense\ServerCapabilities;

/**
 * Sanitizer callbacks for all plugin settings fields.
 *
 * Applied as a PHP trait so that any class that needs to register these
 * callbacks can use `[$this, 'sanitize*']` syntax without an extra object
 * reference, and so that the Settings class exposes these methods for
 * backward-compatible access.
 *
 * @package TypesenseSearch\Admin\Settings
 */
trait Sanitizers
{
    /**
     * Return the searchable fields in settings display order.
     *
     * @return array<string, string>
     */
    public static function getSearchWeightFields(): array
    {
        return [
            'title'       => __('Title', 'typesense-search'),
            'excerpt'     => __('Excerpt', 'typesense-search'),
            'content'     => __('Content', 'typesense-search'),
            'type_name'   => __('Content type name', 'typesense-search'),
            'extra_terms' => __('Extra search terms', 'typesense-search'),
        ];
    }

    /**
     * Return query_by_weights defaults keyed by searchable field.
     *
     * @return array<string, int>
     */
    public static function getDefaultQueryByWeights(): array
    {
        return array_fill_keys(array_keys(self::getSearchWeightFields()), 1);
    }

    /**
     * Sanitize the post types array before saving.
     */
    public function sanitizePostTypes(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_map('sanitize_key', $value);
    }

    /**
     * Sanitize query_by_weights values before saving.
     *
     * @return array<string, int>
     */
    public function sanitizeQueryByWeights(mixed $value): array
    {
        $weights = self::getDefaultQueryByWeights();

        if (!is_array($value)) {
            return $weights;
        }

        foreach (array_keys($weights) as $field) {
            $weight = absint($value[$field] ?? 1);
            $weights[$field] = min(5, max(1, $weight));
        }

        return $weights;
    }

    public function sanitizePinnedResultsEnabled(mixed $value): int
    {
        if (!ServerCapabilities::supportsCurationSets()) {
            return 0;
        }

        return absint($value) ? 1 : 0;
    }

    /**
     * Sanitize the quick search CSS selectors array before saving.
     * Each entry must have a non-empty 'selector' key.
     */
    public function sanitizeQuickSearchSelectors(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $selector = sanitize_text_field($item['selector'] ?? '');
            if (empty($selector)) {
                continue;
            }
            $sibling = !empty($item['sibling']);
            $mobileBehavior = ($item['mobile_behavior'] ?? '') === 'overlay' || !empty($item['mobile_overlay'])
                ? 'overlay'
                : 'regular';
            $result[] = [
                'selector'        => $selector,
                'sibling'         => $sibling,
                'mobile_behavior' => $mobileBehavior,
            ];
        }

        return $result;
    }

    /**
     * Sanitize the facets array before saving.
     * Each facet must have a non-empty 'field' key.
     */
    public function sanitizeFacets(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $field = sanitize_key($item['field'] ?? '');
            if (empty($field)) {
                continue;
            }
            $display = sanitize_text_field($item['display_as'] ?? 'dropdown');
            if (!in_array($display, ['dropdown', 'button_group'], true)) {
                $display = 'dropdown';
            }

            $result[] = [
                'field'       => $field,
                'label'       => sanitize_text_field($item['label'] ?? ''),
                'placeholder' => sanitize_text_field($item['placeholder'] ?? ''),
                'display_as'  => $display,
            ];
        }

        return $result;
    }
}
