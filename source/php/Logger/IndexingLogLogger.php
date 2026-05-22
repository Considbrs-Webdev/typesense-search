<?php

namespace TypesenseSearch\Logger;

/**
 * Logger decorator that preserves PHP error_log output and stores index issues.
 */
class IndexingLogLogger implements LoggerInterface
{
    public function __construct(private LoggerInterface $inner)
    {
    }

    public function error(string $message): void
    {
        $this->inner->error($message);
        IndexingLog::recordMessage('error', $message);
    }

    public function warning(string $message): void
    {
        $this->inner->warning($message);
        IndexingLog::recordMessage('warning', $message);
    }

    public function debug(string $message): void
    {
        $this->inner->debug($message);
    }
}
