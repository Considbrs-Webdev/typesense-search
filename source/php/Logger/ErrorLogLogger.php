<?php

namespace TypesenseSearch\Logger;

/**
 * Class ErrorLogLogger
 *
 * Default LoggerInterface implementation that writes to PHP's error_log().
 *
 * error() and warning() messages are always written.
 * debug() messages are suppressed unless WP_DEBUG is enabled, keeping logs
 * clean on production sites.
 *
 * @package TypesenseSearch\Logger
 */
class ErrorLogLogger implements LoggerInterface
{
    public function error(string $message): void
    {
        error_log($message);
    }

    public function warning(string $message): void
    {
        error_log($message);
    }

    public function debug(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($message);
        }
    }
}
