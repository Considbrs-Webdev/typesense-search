<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

abstract class TestCase extends PhpUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->stubCommonWordPressFunctions();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    protected function instantiateWithoutConstructor(string $className): object
    {
        return (new \ReflectionClass($className))->newInstanceWithoutConstructor();
    }

    private function stubCommonWordPressFunctions(): void
    {
        Functions\when('__')->returnArg(1);
        Functions\when('sanitize_key')->alias(static function (mixed $key): string {
            $key = strtolower((string) $key);

            return (string) preg_replace('/[^a-z0-9_\-]/', '', $key);
        });
        Functions\when('sanitize_text_field')->alias(static function (mixed $value): string {
            return trim(strip_tags((string) $value));
        });
        Functions\when('absint')->alias(static fn (mixed $value): int => abs((int) $value));
        Functions\when('wp_strip_all_tags')->alias(static fn (mixed $value): string => trim(strip_tags((string) $value)));
    }
}
