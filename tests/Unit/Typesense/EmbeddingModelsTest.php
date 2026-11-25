<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Typesense;

use TypesenseSearch\Tests\TestCase;
use TypesenseSearch\Typesense\EmbeddingModels;

class EmbeddingModelsTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('TYPESENSE_EMBEDDING_API_KEY');
        parent::tearDown();
    }

    public function test_presets_include_default_model(): void
    {
        $presets = EmbeddingModels::presets();

        self::assertArrayHasKey(EmbeddingModels::DEFAULT_MODEL, $presets);
        self::assertTrue(EmbeddingModels::isPreset(EmbeddingModels::DEFAULT_MODEL));
        self::assertFalse(EmbeddingModels::isPreset('ts/custom-onnx-model'));
    }

    public function test_validates_local_ts_model_names(): void
    {
        self::assertTrue(EmbeddingModels::isValidModelName('ts/paraphrase-multilingual-mpnet-base-v2'));
        self::assertTrue(EmbeddingModels::isValidModelName('ts/multilingual-e5-small'));
        self::assertTrue(EmbeddingModels::isValidModelName('ts/custom_model.v1'));
        self::assertTrue(EmbeddingModels::isUsableModel('ts/custom_model.v1'));
    }

    public function test_rejects_malformed_model_names(): void
    {
        self::assertFalse(EmbeddingModels::isValidModelName(''));
        self::assertFalse(EmbeddingModels::isValidModelName('__custom__'));
        self::assertFalse(EmbeddingModels::isValidModelName('paraphrase-multilingual-mpnet-base-v2'));
        self::assertFalse(EmbeddingModels::isValidModelName('ts/bad model with spaces'));
        self::assertFalse(EmbeddingModels::isValidModelName('ts/../traversal'));
        self::assertFalse(EmbeddingModels::isValidModelName('unknownprovider/model'));
    }

    public function test_external_provider_requires_api_key(): void
    {
        putenv('TYPESENSE_EMBEDDING_API_KEY');

        self::assertTrue(EmbeddingModels::isValidModelName('openai/text-embedding-3-small'));
        self::assertTrue(EmbeddingModels::requiresExternalApiKey('openai/text-embedding-3-small'));
        self::assertFalse(EmbeddingModels::isUsableModel('openai/text-embedding-3-small'));
        self::assertSame(
            EmbeddingModels::DEFAULT_MODEL,
            EmbeddingModels::sanitizeModel('openai/text-embedding-3-small')
        );

        putenv('TYPESENSE_EMBEDDING_API_KEY=sk-test-secret');

        self::assertTrue(EmbeddingModels::isUsableModel('openai/text-embedding-3-small'));
        self::assertSame(
            'openai/text-embedding-3-small',
            EmbeddingModels::sanitizeModel('openai/text-embedding-3-small')
        );
        self::assertSame(
            [
                'model_name' => 'openai/text-embedding-3-small',
                'api_key'    => 'sk-test-secret',
            ],
            EmbeddingModels::buildModelConfig('openai/text-embedding-3-small')
        );
    }

    public function test_sanitize_model_uses_valid_fallback_when_input_invalid(): void
    {
        self::assertSame(
            'ts/multilingual-e5-small',
            EmbeddingModels::sanitizeModel('invalid model', 'ts/multilingual-e5-small')
        );
        self::assertSame(
            EmbeddingModels::DEFAULT_MODEL,
            EmbeddingModels::sanitizeModel('invalid model', 'also invalid')
        );
    }
}
