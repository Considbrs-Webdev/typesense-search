<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Typesense;

use Brain\Monkey\Functions;
use Mockery;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Tests\TestCase;
use TypesenseSearch\Typesense\Collection;
use TypesenseSearch\Typesense\ServerCapabilities;

class CollectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value, mixed ...$args) => $value);
        Functions\when('get_locale')->justReturn('sv_SE');
    }

    private function settings(array $overrides = []): SettingsRepository
    {
        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('isPinnedResultsEnabled')->andReturn($overrides['pinnedResults'] ?? false);
        $settings->shouldReceive('isSynonymsEnabled')->andReturn($overrides['synonyms'] ?? false);
        $settings->shouldReceive('isStemmingEnabled')->andReturn($overrides['stemming'] ?? false);
        $settings->shouldReceive('isVectorSearchEnabled')->andReturn($overrides['vector'] ?? false);
        $settings->shouldReceive('getVectorModel')->andReturn($overrides['vectorModel'] ?? \TypesenseSearch\Typesense\EmbeddingModels::DEFAULT_MODEL);

        return $settings;
    }

    private function capabilities(array $overrides = []): ServerCapabilities
    {
        $capabilities = Mockery::mock(ServerCapabilities::class);
        $capabilities->shouldReceive('supportsCurationSets')->andReturn($overrides['curationSets'] ?? true);
        $capabilities->shouldReceive('supportsSynonymSets')->andReturn($overrides['synonymSets'] ?? true);
        $capabilities->shouldReceive('supportsStemming')->andReturn($overrides['stemming'] ?? true);
        $capabilities->shouldReceive('supportsAutoEmbedding')->andReturn($overrides['autoEmbedding'] ?? true);

        return $capabilities;
    }

    private function fieldsByName(array $schema): array
    {
        $byName = [];
        foreach ($schema['fields'] as $field) {
            $byName[$field['name']] = $field;
        }

        return $byName;
    }

    // ── synonym_sets ─────────────────────────────────────────────────────────

    public function test_schema_omits_synonym_sets_when_synonyms_disabled(): void
    {
        $schema = Collection::getSchema('my_collection', $this->settings(), $this->capabilities());

        self::assertArrayNotHasKey('synonym_sets', $schema);
    }

    public function test_schema_includes_synonym_sets_when_enabled_and_supported(): void
    {
        $schema = Collection::getSchema(
            'my_collection',
            $this->settings(['synonyms' => true]),
            $this->capabilities(['synonymSets' => true])
        );

        self::assertSame(['wordpress-synonyms-my_collection'], $schema['synonym_sets']);
    }

    public function test_schema_omits_synonym_sets_when_enabled_but_unsupported(): void
    {
        $schema = Collection::getSchema(
            'my_collection',
            $this->settings(['synonyms' => true]),
            $this->capabilities(['synonymSets' => false])
        );

        self::assertArrayNotHasKey('synonym_sets', $schema);
    }

    // ── stem / locale ────────────────────────────────────────────────────────

    public function test_schema_includes_locale_but_omits_stem_when_stemming_disabled(): void
    {
        $schema = Collection::getSchema('my_collection', $this->settings(), $this->capabilities());
        $fields = $this->fieldsByName($schema);

        self::assertArrayNotHasKey('stem', $fields['title']);
        self::assertSame('sv', $fields['title']['locale']);
    }

    public function test_schema_applies_stem_and_locale_only_to_searchable_text_fields(): void
    {
        $schema = Collection::getSchema(
            'my_collection',
            $this->settings(['stemming' => true]),
            $this->capabilities(['stemming' => true])
        );
        $fields = $this->fieldsByName($schema);

        foreach (['title', 'content', 'excerpt', 'extra_terms'] as $name) {
            self::assertTrue($fields[$name]['stem'], "Expected '{$name}' to have stem=true");
            self::assertSame('sv', $fields[$name]['locale'], "Expected '{$name}' to have locale=sv");
        }

        foreach (['id', 'url', 'type', 'type_name', 'date', 'top_most_parent', 'thumbnail', '.*'] as $name) {
            self::assertArrayNotHasKey('stem', $fields[$name], "Did not expect '{$name}' to have a stem key");
        }
    }

    public function test_schema_omits_stem_when_stemming_enabled_but_unsupported(): void
    {
        $schema = Collection::getSchema(
            'my_collection',
            $this->settings(['stemming' => true]),
            $this->capabilities(['stemming' => false])
        );
        $fields = $this->fieldsByName($schema);

        self::assertArrayNotHasKey('stem', $fields['title']);
    }

    public function test_stemming_locale_is_derived_from_site_locale(): void
    {
        Functions\when('get_locale')->justReturn('sv_SE');

        $schema = Collection::getSchema(
            'my_collection',
            $this->settings(['stemming' => true]),
            $this->capabilities(['stemming' => true])
        );

        self::assertSame('sv', $this->fieldsByName($schema)['content']['locale']);
    }

    public function test_stemming_locale_falls_back_to_en_when_site_locale_is_empty(): void
    {
        Functions\when('get_locale')->justReturn('');

        $schema = Collection::getSchema(
            'my_collection',
            $this->settings(['stemming' => true]),
            $this->capabilities(['stemming' => true])
        );

        self::assertSame('en', $this->fieldsByName($schema)['content']['locale']);
    }

    // ── auto-embedding (vector search) ───────────────────────────────────────

    public function test_schema_omits_embedding_field_when_vector_search_disabled(): void
    {
        $schema = Collection::getSchema('my_collection', $this->settings(), $this->capabilities());
        $fields = $this->fieldsByName($schema);

        self::assertArrayNotHasKey('embedding', $fields);
    }

    public function test_schema_includes_embedding_field_with_configured_model_when_enabled(): void
    {
        $schema = Collection::getSchema(
            'my_collection',
            $this->settings(['vector' => true, 'vectorModel' => 'ts/multilingual-e5-small', 'stemming' => true]),
            $this->capabilities(['autoEmbedding' => true, 'stemming' => true])
        );
        $fields = $this->fieldsByName($schema);

        self::assertArrayHasKey('embedding', $fields);
        self::assertSame('float[]', $fields['embedding']['type']);
        self::assertSame(['title', 'excerpt', 'content', 'extra_terms'], $fields['embedding']['embed']['from']);
        self::assertSame('ts/multilingual-e5-small', $fields['embedding']['embed']['model_config']['model_name']);
        self::assertArrayNotHasKey('stem', $fields['embedding']);
        self::assertArrayNotHasKey('locale', $fields['embedding']);
        self::assertSame('.*', $schema['fields'][count($schema['fields']) - 1]['name']);
    }

    public function test_schema_omits_embedding_field_when_enabled_but_server_unsupported(): void
    {
        $schema = Collection::getSchema(
            'my_collection',
            $this->settings(['vector' => true]),
            $this->capabilities(['autoEmbedding' => false])
        );

        self::assertArrayNotHasKey('embedding', $this->fieldsByName($schema));
    }
}
