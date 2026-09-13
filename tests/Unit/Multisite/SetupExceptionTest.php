<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Multisite;

use Brain\Monkey\Functions;
use TypesenseSearch\Multisite\SetupException;
use TypesenseSearch\Tests\TestCase;

class SetupExceptionTest extends TestCase
{
    public function test_unknown_error_never_displays_credentials(): void
    {
        $message = SetupException::describe(new \RuntimeException('https://secret-key@server.test'));
        self::assertStringNotContainsString('secret-key', $message);
        self::assertStringContainsString('Verify the server', $message);
    }

    /** @dataProvider errors */
    public function test_known_failures_have_translatable_actionable_messages(string $class, string $expected): void
    {
        Functions\when('__')->alias(static fn ($message, $domain) => $domain . ':' . $message);
        $message = SetupException::describe(new $class('sensitive SDK response'));
        self::assertStringStartsWith('typesense-search:', $message);
        self::assertStringContainsString($expected, $message);
        self::assertStringNotContainsString('sensitive', $message);
    }

    public static function errors(): array
    {
        return [
            [\Typesense\Exceptions\ObjectNotFound::class, 'index is missing'],
            [\Typesense\Exceptions\RequestUnauthorized::class, 'rejected the API key'],
            [\Typesense\Exceptions\Timeout::class, 'could not be reached'],
            [\Typesense\Exceptions\HTTPStatus0Error::class, 'could not be reached'],
            [\Typesense\Exceptions\ServiceUnavailable::class, 'could not be reached'],
        ];
    }

    public function test_authored_error_keeps_actionable_message(): void
    {
        self::assertSame('Retry setup.', SetupException::describe(new SetupException('Retry setup.')));
    }
}
