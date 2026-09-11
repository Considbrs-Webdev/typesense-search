<?php

namespace TypesenseSearch\Typesense;

/** Only explicitly authored, pre-sanitized provisioning-key errors — never wraps raw SDK text. */
class ProvisioningException extends \RuntimeException
{
    public static function unavailable(): self
    {
        return new self(__('No provisioning key is configured. Run "wp ... typesense network setup" with TYPESENSE_PROVISIONING_KEY set for that command, or configure a permanent provisioning key to enable this from the admin screens.', 'typesense-search'));
    }

    public static function destinationConflict(): self
    {
        return new self(__('The provisioning key is restricted to a different, server-configured destination than the one currently configured. Update the connection settings or the provisioning destination so they match.', 'typesense-search'));
    }
}
