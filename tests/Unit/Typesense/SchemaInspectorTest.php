<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Typesense;

use Brain\Monkey\Functions;
use TypesenseSearch\Tests\TestCase;
use TypesenseSearch\Typesense\EmbeddingModels;
use TypesenseSearch\Typesense\SchemaInspector;

class SchemaInspectorTest extends TestCase
{
    public function test_analyze_schema_extracts_embedding_and_stemming_metadata(): void
    {
        $analysis = SchemaInspector::analyzeSchema([
            'name'   => 'mun_site__production_b1',
            'fields' => [
                ['name' => 'title', 'type' => 'string', 'locale' => 'sv', 'stem' => true],
                ['name' => 'content', 'type' => 'string', 'locale' => 'sv', 'stem' => true],
                ['name' => 'excerpt', 'type' => 'string', 'locale' => 'sv', 'stem' => true],
                ['name' => 'extra_terms', 'type' => 'string', 'locale' => 'sv', 'stem' => true],
                [
                    'name'  => 'embedding',
                    'type'  => 'float[]',
                    'embed' => [
                        'from'         => ['title', 'excerpt', 'content', 'extra_terms'],
                        'model_config' => [
                            'model_name' => EmbeddingModels::DEFAULT_MODEL,
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('mun_site__production_b1', $analysis['collection']);
        self::assertTrue($analysis['has_embedding']);
        self::assertSame(EmbeddingModels::DEFAULT_MODEL, $analysis['embedding_model']);
        self::assertSame(['title', 'excerpt', 'content', 'extra_terms'], $analysis['embedding_from']);
        self::assertTrue($analysis['has_stemming']);
        self::assertSame('sv', $analysis['locale']);
    }

    public function test_analyze_schema_handles_plain_lexical_schema(): void
    {
        $analysis = SchemaInspector::analyzeSchema([
            'name'   => 'plain_col',
            'fields' => [
                ['name' => 'title', 'type' => 'string', 'locale' => 'en'],
                ['name' => 'content', 'type' => 'string', 'locale' => 'en'],
                ['name' => 'excerpt', 'type' => 'string', 'locale' => 'en'],
                ['name' => 'extra_terms', 'type' => 'string', 'locale' => 'en'],
            ],
        ]);

        self::assertFalse($analysis['has_embedding']);
        self::assertSame('', $analysis['embedding_model']);
        self::assertFalse($analysis['has_stemming']);
        self::assertSame('en', $analysis['locale']);
    }

    public function test_verified_state_rejects_mismatched_remote_or_collection(): void
    {
        $saved = null;
        Functions\when('update_option')->alias(static function (string $key, mixed $val) use (&$saved): bool {
            $saved = $val;
            return true;
        });
        Functions\when('get_option')->alias(static function () use (&$saved): mixed {
            return $saved;
        });

        SchemaInspector::recordFromSchema('https://search.example.com/', [
            'name'   => 'col_a',
            'fields' => [
                [
                    'name'  => 'embedding',
                    'type'  => 'float[]',
                    'embed' => [
                        'from'         => ['title'],
                        'model_config' => ['model_name' => 'ts/multilingual-e5-small'],
                    ],
                ],
            ],
        ]);

        self::assertTrue(SchemaInspector::isEmbeddingReady('https://search.example.com', 'col_a', 'ts/multilingual-e5-small'));
        self::assertFalse(SchemaInspector::isEmbeddingReady('https://search.example.com', 'col_a', EmbeddingModels::DEFAULT_MODEL));
        self::assertFalse(SchemaInspector::isEmbeddingReady('https://other.example.com', 'col_a', 'ts/multilingual-e5-small'));
        self::assertFalse(SchemaInspector::isEmbeddingReady('https://search.example.com', 'col_b', 'ts/multilingual-e5-small'));
    }

    public function test_evaluate_compatibility_reports_unverified_and_rebuild_required_states(): void
    {
        $unverified = SchemaInspector::evaluateCompatibility(true, EmbeddingModels::DEFAULT_MODEL, false, null);
        self::assertFalse($unverified['ok']);
        self::assertSame('unverified', $unverified['status']);

        $missingEmbed = SchemaInspector::evaluateCompatibility(
            true,
            EmbeddingModels::DEFAULT_MODEL,
            false,
            ['has_embedding' => false, 'embedding_model' => '', 'has_stemming' => false]
        );
        self::assertFalse($missingEmbed['ok']);
        self::assertSame('rebuild_required', $missingEmbed['status']);

        $modelMismatch = SchemaInspector::evaluateCompatibility(
            true,
            'ts/multilingual-e5-small',
            false,
            ['has_embedding' => true, 'embedding_model' => EmbeddingModels::DEFAULT_MODEL, 'has_stemming' => false]
        );
        self::assertFalse($modelMismatch['ok']);
        self::assertSame('rebuild_required', $modelMismatch['status']);

        $stale = SchemaInspector::evaluateCompatibility(
            false,
            EmbeddingModels::DEFAULT_MODEL,
            false,
            ['has_embedding' => true, 'embedding_model' => EmbeddingModels::DEFAULT_MODEL, 'has_stemming' => false]
        );
        self::assertTrue($stale['ok']);
        self::assertSame('stale_embedding', $stale['status']);

        $ok = SchemaInspector::evaluateCompatibility(
            true,
            EmbeddingModels::DEFAULT_MODEL,
            true,
            ['has_embedding' => true, 'embedding_model' => EmbeddingModels::DEFAULT_MODEL, 'has_stemming' => true]
        );
        self::assertTrue($ok['ok']);
        self::assertSame('ok', $ok['status']);
    }
}
