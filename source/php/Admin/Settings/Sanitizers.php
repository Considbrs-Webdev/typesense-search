<?php

namespace TypesenseSearch\Admin\Settings;

use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Typesense\AdminApi;
use TypesenseSearch\Typesense\EmbeddingModels;
use TypesenseSearch\Typesense\ServerCapabilities;
use TypesenseSearch\Typesense\VectorSearchPresets;

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

    /**
     * Sanitize the admin (indexing) key: an empty submission means "keep the
     * saved secret", never "clear it". Clearing requires the explicit
     * companion checkbox, since a blank field alone is ambiguous. Nonce
     * verification already happened in options.php via settings_fields()
     * before this callback runs.
     */
    public function sanitizeAdminKey(mixed $value): string
    {
        $value = sanitize_text_field((string) $value);

        if ($value !== '') {
            return $value;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!empty($_POST[OptionKeys::OPTION_ADMIN_KEY . '_clear'])) {
            return '';
        }

        return (string) get_option(OptionKeys::OPTION_ADMIN_KEY, '');
    }

    public function sanitizePinnedResultsEnabled(mixed $value): int
    {
        if (!(new ServerCapabilities(new AdminApi(new SettingsRepository())))->supportsCurationSets()) {
            return 0;
        }

        return absint($value) ? 1 : 0;
    }

    public function sanitizeSynonymsEnabled(mixed $value): int
    {
        if (!(new ServerCapabilities(new AdminApi(new SettingsRepository())))->supportsSynonymSets()) {
            return 0;
        }

        return absint($value) ? 1 : 0;
    }

    public function sanitizeExternalPagesEnabled(mixed $value): int
    {
        return absint($value) ? 1 : 0;
    }

    public function sanitizeStemmingEnabled(mixed $value): int
    {
        if (!(new ServerCapabilities(new AdminApi(new SettingsRepository())))->supportsStemming()) {
            return 0;
        }

        return absint($value) ? 1 : 0;
    }

    public function sanitizeVectorEnabled(mixed $value): int
    {
        if (!(new ServerCapabilities(new AdminApi(new SettingsRepository())))->supportsAutoEmbedding()) {
            return 0;
        }

        return absint($value) ? 1 : 0;
    }

    public function sanitizeVectorModel(mixed $value): string
    {
        $raw = is_string($value) ? trim(sanitize_text_field($value)) : '';
        $previous = (string) get_option(OptionKeys::OPTION_VECTOR_MODEL, EmbeddingModels::DEFAULT_MODEL);

        if ($raw !== '' && !EmbeddingModels::isUsableModel($raw)) {
            if (function_exists('add_settings_error')) {
                $reason = EmbeddingModels::isValidModelName($raw) && EmbeddingModels::requiresExternalApiKey($raw)
                    ? __(
                        'External embedding models require the TYPESENSE_EMBEDDING_API_KEY constant or environment variable. Kept the previous embedding model.',
                        'typesense-search'
                    )
                    : __(
                        'Invalid embedding model name. Enter a preset or a valid model identifier such as "ts/model-name". Kept the previous embedding model.',
                        'typesense-search'
                    );
                add_settings_error(OptionKeys::OPTION_VECTOR_MODEL, 'invalid_vector_model', $reason, 'error');
            }

            return EmbeddingModels::sanitizeModel($previous);
        }

        return EmbeddingModels::sanitizeModel($raw, $previous);
    }

    public function sanitizeVectorStrategy(mixed $value): string
    {
        if ($value === null) {
            return VectorSearchPresets::sanitizeStrategy(
                get_option(OptionKeys::OPTION_VECTOR_STRATEGY, VectorSearchPresets::DEFAULT_STRATEGY)
            );
        }

        return VectorSearchPresets::sanitizeStrategy($value);
    }

    public function sanitizeVectorRankingProfile(mixed $value): string
    {
        if ($value === null) {
            return VectorSearchPresets::sanitizeProfile(
                get_option(OptionKeys::OPTION_VECTOR_RANKING_PROFILE, VectorSearchPresets::DEFAULT_PROFILE)
            );
        }

        return VectorSearchPresets::sanitizeProfile($value);
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitizeVectorCustomParams(mixed $value): array
    {
        $settingsRepo = new SettingsRepository();
        $model        = $settingsRepo->getVectorModel();

        if (
            !$settingsRepo->network()->isNetworkActivated()
            && !\TypesenseSearch\ConstantsLoader::isDefinedAsConstant(OptionKeys::OPTION_VECTOR_MODEL)
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by options.php via settings_fields().
            && isset($_POST[OptionKeys::OPTION_VECTOR_MODEL])
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            && is_string($_POST[OptionKeys::OPTION_VECTOR_MODEL])
        ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $postedModel = trim(sanitize_text_field(stripslashes($_POST[OptionKeys::OPTION_VECTOR_MODEL])));
            if ($postedModel !== '' && EmbeddingModels::isUsableModel($postedModel)) {
                $model = EmbeddingModels::sanitizeModel($postedModel, $model);
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by options.php via settings_fields().
        $rawProfile = $_POST[OptionKeys::OPTION_VECTOR_RANKING_PROFILE]
            ?? get_option(OptionKeys::OPTION_VECTOR_RANKING_PROFILE, VectorSearchPresets::DEFAULT_PROFILE);
        $activeProfile = VectorSearchPresets::sanitizeProfile(
            is_string($rawProfile) ? stripslashes($rawProfile) : $rawProfile
        );

        if ($activeProfile !== VectorSearchPresets::PROFILE_CUSTOM) {
            return VectorSearchPresets::sanitizeCustomParams($value, $model, $activeProfile);
        }

        $saved  = get_option(OptionKeys::OPTION_VECTOR_CUSTOM_PARAMS, []);
        $merged = is_array($saved)
            ? array_merge($saved, is_array($value) ? $value : [])
            : $value;

        return VectorSearchPresets::sanitizeCustomParams($merged, $model, VectorSearchPresets::PROFILE_BALANCED);
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
