<?php

namespace TypesenseSearch\Multisite;

/** Only explicitly authored errors may expose their message to administrators. */
class SetupException extends \RuntimeException
{
    public static function describe(\Throwable $error): string
    {
        if ($error instanceof self) {
            return $error->getMessage();
        }
        if ($error instanceof \Typesense\Exceptions\ObjectNotFound) {
            return __('The index is missing on the server. Retry setup to recreate it, then run typesense index.', 'typesense-search');
        }
        if ($error instanceof \Typesense\Exceptions\RequestUnauthorized) {
            return __('The server rejected the API key. Check the network admin key and retry setup to repair the site search key.', 'typesense-search');
        }
        if ($error instanceof \Typesense\Exceptions\Timeout || $error instanceof \Typesense\Exceptions\HTTPStatus0Error
            || $error instanceof \Typesense\Exceptions\ServiceUnavailable
            || $error instanceof \Http\Client\Exception\NetworkException) {
            return __('The server could not be reached. Check the host, TLS and network connection, then retry.', 'typesense-search');
        }
        return __('Typesense operation failed. Verify the server, credentials and collection in Network Admin, then retry.', 'typesense-search');
    }
}
