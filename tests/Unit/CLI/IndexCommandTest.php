<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\CLI;

use ReflectionClass;
use ReflectionMethod;
use TypesenseSearch\CLI\IndexCommand;
use TypesenseSearch\Tests\TestCase;

/**
 * Characterization tests for IndexCommand.
 *
 * Verifies that IndexCommand remains a thin delegation layer — it must expose
 * all the WP-CLI subcommands as public methods with the correct @subcommand
 * annotations so WP-CLI can discover and route them correctly.
 */
class IndexCommandTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ReflectionClass(IndexCommand::class);
    }

    // ── Subcommand method existence ────────────────────────────────────────────

    /**
     * @dataProvider subcommandMethodProvider
     */
    public function test_subcommand_method_exists_and_is_public(string $method): void
    {
        self::assertTrue(
            $this->reflection->hasMethod($method),
            sprintf('IndexCommand must have a public method "%s".', $method)
        );

        $refMethod = $this->reflection->getMethod($method);
        self::assertTrue(
            $refMethod->isPublic(),
            sprintf('IndexCommand::%s() must be public so WP-CLI can invoke it.', $method)
        );
    }

    /** @return array<string, array{string}> */
    public static function subcommandMethodProvider(): array
    {
        return [
            'index'                   => ['index'],
            'rebuild'                 => ['rebuild'],
            'clear'                   => ['clear'],
            'syncExternal'            => ['syncExternal'],
            'listExternal'            => ['listExternal'],
            'pruneSearchStatistics'   => ['pruneSearchStatistics'],
            'populateSearchLog'       => ['populateSearchLog'],
        ];
    }

    // ── @subcommand annotations ───────────────────────────────────────────────

    /**
     * @dataProvider subcommandAnnotationProvider
     */
    public function test_method_carries_subcommand_annotation(
        string $method,
        string $expectedSubcommand
    ): void {
        $refMethod = $this->reflection->getMethod($method);
        $docBlock  = (string) $refMethod->getDocComment();

        self::assertStringContainsString(
            '@subcommand ' . $expectedSubcommand,
            $docBlock,
            sprintf(
                'IndexCommand::%s() must carry @subcommand %s so WP-CLI maps the subcommand correctly.',
                $method,
                $expectedSubcommand
            )
        );
    }

    /** @return array<string, array{string, string}> */
    public static function subcommandAnnotationProvider(): array
    {
        return [
            'index'                   => ['index',                 'index'],
            'rebuild'                 => ['rebuild',               'rebuild'],
            'clear'                   => ['clear',                 'clear'],
            'syncExternal'            => ['syncExternal',          'sync-external'],
            'listExternal'            => ['listExternal',          'list-external'],
            'pruneSearchStatistics'   => ['pruneSearchStatistics', 'prune-search-statistics'],
            'populateSearchLog'       => ['populateSearchLog',     'populate-search-log'],
        ];
    }

    // ── @when annotations ─────────────────────────────────────────────────────

    /**
     * @dataProvider subcommandMethodProvider
     */
    public function test_method_carries_when_after_wp_load_annotation(string $method): void
    {
        $refMethod = $this->reflection->getMethod($method);
        $docBlock  = (string) $refMethod->getDocComment();

        self::assertStringContainsString(
            '@when after_wp_load',
            $docBlock,
            sprintf(
                'IndexCommand::%s() must carry @when after_wp_load to ensure WordPress is bootstrapped.',
                $method
            )
        );
    }

    // ── Method signature ──────────────────────────────────────────────────────

    /**
     * @dataProvider subcommandMethodProvider
     */
    public function test_method_accepts_args_and_assocArgs(string $method): void
    {
        $refMethod = $this->reflection->getMethod($method);
        $params    = $refMethod->getParameters();

        self::assertCount(
            2,
            $params,
            sprintf('IndexCommand::%s() must accept exactly two parameters ($args, $assocArgs).', $method)
        );

        self::assertSame('args',      $params[0]->getName());
        self::assertSame('assocArgs', $params[1]->getName());
    }

    // ── IndexCommand is thin (no non-delegation logic) ────────────────────────

    public function test_IndexCommand_has_no_business_logic_methods(): void
    {
        $publicMethods = array_filter(
            $this->reflection->getMethods(ReflectionMethod::IS_PUBLIC),
            fn(ReflectionMethod $m) => !$m->isConstructor()
                && $m->getDeclaringClass()->getName() === IndexCommand::class
        );

        $methodNames = array_map(fn(ReflectionMethod $m) => $m->getName(), $publicMethods);

        $expectedPublicMethods = [
            'index',
            'rebuild',
            'clear',
            'syncExternal',
            'listExternal',
            'pruneSearchStatistics',
            'populateSearchLog',
        ];

        sort($methodNames);
        sort($expectedPublicMethods);

        self::assertSame(
            $expectedPublicMethods,
            $methodNames,
            'IndexCommand should expose exactly the WP-CLI subcommand methods and nothing else.'
        );
    }
}
