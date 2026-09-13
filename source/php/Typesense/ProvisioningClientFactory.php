<?php

namespace TypesenseSearch\Typesense;

use Typesense\Client;

/**
 * Builds the Typesense client used exclusively for key-management operations
 * (create/list/delete search keys).
 *
 * This is deliberately the only place a provisioning client can be built.
 * Callers must always pass an already-trusted remote — one that came from
 * saved settings or a PHP constant, never directly from request input — so
 * that a form-supplied destination can never receive the provisioning key.
 *
 * @package TypesenseSearch\Typesense
 */
class ProvisioningClientFactory
{
    /**
     * @param string $trustedRemote The server-configured remote to connect to.
     *                               Must never originate from unsaved form/AJAX input.
     */
    public static function fromTrustedRemote(string $trustedRemote): Client
    {
        if (!ProvisioningCredentials::hasKey()) {
            throw ProvisioningException::unavailable();
        }

        $override = ProvisioningCredentials::getRemoteOverride();
        if ($override !== '' && rtrim($override, '/') !== rtrim($trustedRemote, '/')) {
            throw ProvisioningException::destinationConflict();
        }

        return ClientFactory::build($trustedRemote, ProvisioningCredentials::getKey());
    }
}
