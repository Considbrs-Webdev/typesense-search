<?php

namespace TypesenseSearch\Typesense;

/**
 * Validated registry and helpers for Typesense auto-embedding models and modes.
 */
class EmbeddingModels
{
    public const DEFAULT_MODEL = 'ts/paraphrase-multilingual-mpnet-base-v2';
    public const CUSTOM_CHOICE = '__custom__';

    public const EMBED_SOURCE_FIELDS = ['title', 'excerpt', 'content', 'extra_terms'];

    public const MODE_HYBRID   = 'hybrid';
    public const MODE_KEYWORD  = 'keyword';
    public const MODE_SEMANTIC = 'semantic';

    public const SEARCH_MODES = [
        self::MODE_HYBRID,
        self::MODE_KEYWORD,
        self::MODE_SEMANTIC,
    ];

    public const DEFAULT_SEARCH_MODE = self::MODE_HYBRID;

    private const MODEL_PATTERN = '~^(ts|openai|palm|gcp|azure)/[A-Za-z0-9._-]+(?:/[A-Za-z0-9._-]+)*$~';
    private const MAX_LENGTH    = 191;

    /**
     * Curated built-in local ONNX models hosted by Typesense (`ts/...`).
     *
     * @return array<string, string>
     */
    public static function presets(): array
    {
        return [
            'ts/paraphrase-multilingual-mpnet-base-v2' => __(
                'Multilingual MPNet Base v2 (ts/paraphrase-multilingual-mpnet-base-v2) — Recommended for Swedish & multilingual',
                'typesense-search'
            ),
            'ts/multilingual-e5-small' => __(
                'Multilingual E5 Small (ts/multilingual-e5-small) — Lower memory & faster CPU',
                'typesense-search'
            ),
            'ts/multilingual-e5-base' => __(
                'Multilingual E5 Base (ts/multilingual-e5-base) — Balanced multilingual accuracy',
                'typesense-search'
            ),
            'ts/all-MiniLM-L12-v2' => __(
                'MiniLM L12 v2 (ts/all-MiniLM-L12-v2) — Lightweight (primarily English)',
                'typesense-search'
            ),
        ];
    }

    public static function isPreset(string $model): bool
    {
        return array_key_exists($model, self::presets());
    }

    public static function isValidModelName(string $model): bool
    {
        if ($model === '' || strlen($model) > self::MAX_LENGTH) {
            return false;
        }
        if (str_contains($model, '..')) {
            return false;
        }

        return preg_match(self::MODEL_PATTERN, $model) === 1;
    }

    public static function requiresExternalApiKey(string $model): bool
    {
        return $model !== '' && !str_starts_with($model, 'ts/');
    }

    /**
     * External provider API key (e.g. OpenAI) supplied via PHP constant or environment.
     */
    public static function externalApiKey(): string
    {
        if (defined('TYPESENSE_EMBEDDING_API_KEY') && is_string(TYPESENSE_EMBEDDING_API_KEY)) {
            $val = trim(TYPESENSE_EMBEDDING_API_KEY);
            if ($val !== '') {
                return $val;
            }
        }
        $env = getenv('TYPESENSE_EMBEDDING_API_KEY');

        return is_string($env) ? trim($env) : '';
    }

    public static function isUsableModel(string $model): bool
    {
        if (!self::isValidModelName($model)) {
            return false;
        }
        if (self::requiresExternalApiKey($model) && self::externalApiKey() === '') {
            return false;
        }

        return true;
    }

    public static function sanitizeModel(mixed $value, ?string $fallback = null): string
    {
        $candidate = is_string($value) ? trim(sanitize_text_field($value)) : '';
        if (self::isUsableModel($candidate)) {
            return $candidate;
        }

        if ($fallback !== null && self::isUsableModel($fallback)) {
            return $fallback;
        }

        return self::DEFAULT_MODEL;
    }

    public static function sanitizeSearchMode(mixed $value): string
    {
        $mode = is_string($value) ? trim($value) : '';

        return in_array($mode, self::SEARCH_MODES, true) ? $mode : self::DEFAULT_SEARCH_MODE;
    }

    /**
     * Build the `model_config` payload for Typesense collection creation.
     *
     * @return array<string, string>
     */
    public static function buildModelConfig(string $model): array
    {
        $sanitized = self::sanitizeModel($model);
        $config    = ['model_name' => $sanitized];

        if (self::requiresExternalApiKey($sanitized)) {
            $apiKey = self::externalApiKey();
            if ($apiKey !== '') {
                $config['api_key'] = $apiKey;
            }
        }

        return $config;
    }
}
