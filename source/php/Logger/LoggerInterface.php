<?php

namespace TypesenseSearch\Logger;

/**
 * Interface LoggerInterface
 *
 * Minimal logging contract used within the plugin. Injecting this interface
 * (rather than calling error_log() directly) lets you swap the implementation
 * without touching strategies or other consumers — for example to route
 * messages to a Sentry client, a custom WP debug log, or a test spy.
 *
 * @package TypesenseSearch\Logger
 */
interface LoggerInterface
{
    /**
     * Log an error-level message (unexpected failure that should be noticed).
     */
    public function error(string $message): void;

    /**
     * Log a warning-level message (recoverable issue worth knowing about).
     */
    public function warning(string $message): void;

    /**
     * Log a debug-level message (verbose detail for development).
     * Implementations may suppress this in production.
     */
    public function debug(string $message): void;
}
