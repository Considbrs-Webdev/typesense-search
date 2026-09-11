<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Typesense;

use TypesenseSearch\Tests\TestCase;
use TypesenseSearch\Typesense\ProvisioningCredentials;

/**
 * Constants can only be defined once per PHP process, so each scenario below
 * runs in its own process (@runInSeparateProcess) to keep them independent.
 */
class ProvisioningCredentialsTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_no_key_available_when_neither_constant_nor_env_are_set(): void
    {
        putenv('TYPESENSE_PROVISIONING_KEY');
        self::assertSame('', ProvisioningCredentials::getKey());
        self::assertFalse(ProvisioningCredentials::hasKey());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_environment_variable_is_used_when_no_constant_is_defined(): void
    {
        putenv('TYPESENSE_PROVISIONING_KEY=from-env');
        self::assertSame('from-env', ProvisioningCredentials::getKey());
        self::assertTrue(ProvisioningCredentials::hasKey());
        putenv('TYPESENSE_PROVISIONING_KEY');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_defined_constant_takes_priority_over_environment_variable(): void
    {
        putenv('TYPESENSE_PROVISIONING_KEY=from-env');
        define('TYPESENSE_PROVISIONING_KEY', 'from-constant');
        self::assertSame('from-constant', ProvisioningCredentials::getKey());
        putenv('TYPESENSE_PROVISIONING_KEY');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_empty_constant_means_unavailable_and_does_not_fall_back_to_environment(): void
    {
        putenv('TYPESENSE_PROVISIONING_KEY=from-env');
        define('TYPESENSE_PROVISIONING_KEY', '');
        self::assertSame('', ProvisioningCredentials::getKey());
        self::assertFalse(ProvisioningCredentials::hasKey());
        putenv('TYPESENSE_PROVISIONING_KEY');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_no_remote_override_means_any_trusted_remote_is_accepted(): void
    {
        define('TYPESENSE_PROVISIONING_KEY', 'secret');
        self::assertSame('', ProvisioningCredentials::getRemoteOverride());
        self::assertTrue(ProvisioningCredentials::isAvailableFor('https://search.example.com'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_remote_override_must_match_the_trusted_remote(): void
    {
        define('TYPESENSE_PROVISIONING_KEY', 'secret');
        define('TYPESENSE_PROVISIONING_REMOTE', 'https://search.example.com/');
        self::assertTrue(ProvisioningCredentials::isAvailableFor('https://search.example.com'));
        self::assertFalse(ProvisioningCredentials::isAvailableFor('https://other.example.com'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_unavailable_without_a_key_regardless_of_remote(): void
    {
        self::assertFalse(ProvisioningCredentials::isAvailableFor('https://search.example.com'));
    }
}
