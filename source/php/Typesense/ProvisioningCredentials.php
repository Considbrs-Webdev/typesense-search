<?php

namespace TypesenseSearch\Typesense;

/**
 * Reads the provisioning key used to create/delete Typesense API keys.
 *
 * Deliberately independent of SettingsRepository/options: the provisioning
 * key must never be stored in the database, rendered in a settings form, or
 * driven by request input. It only ever comes from a PHP constant or an
 * environment variable, read fresh on every call.
 *
 * Priority: a defined TYPESENSE_PROVISIONING_KEY constant always wins, even
 * when empty (an empty constant means "explicitly unavailable", not "check
 * the environment instead"). Only when the constant is undefined does the
 * environment variable apply.
 *
 * @package TypesenseSearch\Typesense
 */
class ProvisioningCredentials
{
    public static function getKey(): string
    {
        if (defined('TYPESENSE_PROVISIONING_KEY')) {
            $value = constant('TYPESENSE_PROVISIONING_KEY');
            return is_string($value) ? $value : '';
        }

        $env = getenv('TYPESENSE_PROVISIONING_KEY');
        return is_string($env) ? $env : '';
    }

    public static function hasKey(): bool
    {
        return self::getKey() !== '';
    }

    /**
     * A server-configured override for the destination the provisioning key
     * may be sent to. Empty means "no override — trust the caller's already
     * configured remote".
     */
    public static function getRemoteOverride(): string
    {
        if (defined('TYPESENSE_PROVISIONING_REMOTE')) {
            $value = constant('TYPESENSE_PROVISIONING_REMOTE');
            return is_string($value) ? $value : '';
        }

        return '';
    }

    /**
     * True when a provisioning key exists and, if a remote override is
     * configured, the given (already-trusted) remote matches it.
     */
    public static function isAvailableFor(string $trustedRemote): bool
    {
        if (!self::hasKey()) {
            return false;
        }

        $override = self::getRemoteOverride();

        return $override === '' || rtrim($override, '/') === rtrim($trustedRemote, '/');
    }
}
