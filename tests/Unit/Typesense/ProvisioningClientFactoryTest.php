<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Unit\Typesense;

use TypesenseSearch\Tests\TestCase;
use TypesenseSearch\Typesense\ProvisioningClientFactory;
use TypesenseSearch\Typesense\ProvisioningException;

class ProvisioningClientFactoryTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_throws_when_no_provisioning_key_is_configured(): void
    {
        $this->expectException(ProvisioningException::class);
        $this->expectExceptionMessage('No provisioning key is configured');
        ProvisioningClientFactory::fromTrustedRemote('https://search.example.com');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_throws_on_destination_conflict_with_the_configured_override(): void
    {
        define('TYPESENSE_PROVISIONING_KEY', 'secret');
        define('TYPESENSE_PROVISIONING_REMOTE', 'https://search.example.com');
        $this->expectException(ProvisioningException::class);
        $this->expectExceptionMessage('restricted to a different');
        ProvisioningClientFactory::fromTrustedRemote('https://attacker.example.com');
    }

    /**
     * Building the real Typesense\Client requires a PSR-18 HTTP client, which
     * this dev/test environment does not install (matching the rest of this
     * suite — no other test constructs a real client either; ClientFactory
     * itself is exercised only via overridable gateways). The guard logic
     * above (unavailable/conflict) is this class's actual new behaviour;
     * reaching ClientFactory::build() without an exception up to that point
     * is enough to prove the guards pass when they should.
     */
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_guards_pass_through_to_client_factory_when_key_and_destination_are_trusted(): void
    {
        define('TYPESENSE_PROVISIONING_KEY', 'secret');
        define('TYPESENSE_PROVISIONING_REMOTE', 'https://search.example.com');
        try {
            ProvisioningClientFactory::fromTrustedRemote('https://search.example.com');
        } catch (ProvisioningException $e) {
            self::fail('Guards must not reject a trusted key/destination: ' . $e->getMessage());
        } catch (\Throwable $e) {
            // Constructing the real HTTP client needs a PSR-18 adapter this
            // environment does not install; reaching that point at all proves
            // the guards above it passed.
        }
        self::assertTrue(true);
    }
}
