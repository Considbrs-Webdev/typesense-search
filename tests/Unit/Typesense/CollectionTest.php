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
    }

    private function settings(array $overrides = []): SettingsRepository
    {
        $settings = Mockery::mock(SettingsRepository::class);
        $settings->shouldReceive('isPinnedResultsEnabled')->andReturn($overrides['pinnedResults'] ?? false);
        $settings->shouldReceive('isSynonymsEnabled')->andReturn($overrides['synonyms'] ?? false);
        $settings->shouldReceive('isStemmingEnabled')->andReturn($overrides['stemming'] ?? false);

        return $settings;
    }

    private function capabilities(array $overrides = []): ServerCapabilities
    {
        $capabilities = Mockery::mock(ServerCapabilities::class);
        $capabilities->shouldReceive('supportsCurationSets')->andReturn($overrides['curationSets'] ?? true);
        $capabilities->shouldReceive('supportsSynonymSets')->andReturn($overrides['synonymSets'] ?? true);
        $capabilities->shouldReceive('supportsStemming')->andReturn($overrides['stemming'] ?? true);

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

    public function test_schema_omits_stem_and_locale_when_stemming_disabled(): void
    {
        $schema = Collection::getSchema('my_collection', $this->settings(), $this->capabilities());
        $fields = $this->fieldsByName($schema);

        self::assertArrayNotHasKey('stem', $fields['title']);
        self::assertArrayNotHasKey('locale', $fields['title']);
    }

    public function test_schema_applies_stem_and_locale_only_to_searchable_text_fields(): void
    {
        Functions\when('get_locale')->justReturn('sv_SE');

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
}
