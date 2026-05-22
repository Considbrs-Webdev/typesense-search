<?php

namespace TypesenseSearch\Logger;

/**
 * Stores the latest indexing run and its document-level issues.
 */
class IndexingLog
{
    public const OPTION_NAME = 'typesense_search_indexing_log';
    private const MAX_ENTRIES = 200;
    private const DUPLICATE_WINDOW_SECONDS = 10;

    private static ?string $currentRunId = null;

    public static function beginRun(string $source, string $label, bool $clearEntries = true): string
    {
        $runId = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('ts-run-', true);
        $existingLog = self::getLog();

        update_option(self::OPTION_NAME, [
            'last_run' => [
                'id'         => $runId,
                'source'     => sanitize_key($source),
                'label'      => sanitize_text_field($label),
                'status'     => 'running',
                'started_at' => time(),
                'ended_at'   => null,
                'indexed'    => 0,
                'skipped'    => 0,
                'failed'     => 0,
                'message'    => '',
            ],
            'entries'  => $clearEntries ? [] : $existingLog['entries'],
        ], false);

        self::$currentRunId = $runId;

        return $runId;
    }

    /**
     * @param array{indexed?: int, skipped?: int, failed?: int} $counts
     */
    public static function endRun(array $counts, string $message = ''): void
    {
        $log = self::getLog();
        if (empty($log['last_run'])) {
            self::$currentRunId = null;
            return;
        }

        $failed = max(0, (int) ($counts['failed'] ?? $log['last_run']['failed'] ?? 0));

        $log['last_run']['status']   = $failed > 0 ? 'error' : 'success';
        $log['last_run']['ended_at'] = time();
        $log['last_run']['indexed']  = max(0, (int) ($counts['indexed'] ?? 0));
        $log['last_run']['skipped']  = max(0, (int) ($counts['skipped'] ?? 0));
        $log['last_run']['failed']   = $failed;
        $log['last_run']['message']  = sanitize_text_field($message);

        update_option(self::OPTION_NAME, $log, false);
        self::$currentRunId = null;
    }

    public static function recordMessage(string $level, string $message): void
    {
        if (!in_array($level, ['error', 'warning'], true)) {
            return;
        }

        $context = self::parseMessage($message);
        self::recordIssue($level, $context['message'], $context);
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function recordIssue(string $level, string $message, array $context = []): void
    {
        if (!in_array($level, ['error', 'warning'], true)) {
            $level = 'error';
        }

        $log = self::getLog();
        if (self::isRecentDuplicate($log['entries'], $level, $message, $context)) {
            return;
        }

        $createdAdHocRun = false;
        if (self::$currentRunId === null) {
            self::beginRun('wp-save', __('WordPress save', 'typesense-search'), false);
            $createdAdHocRun = true;
            $log = self::getLog();
        }

        $entry = [
            'id'             => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('ts-log-', true),
            'run_id'         => self::$currentRunId,
            'timestamp'      => time(),
            'level'          => $level,
            'strategy'       => sanitize_text_field((string) ($context['strategy'] ?? '')),
            'document_id'    => sanitize_text_field((string) ($context['document_id'] ?? '')),
            'document_label' => sanitize_text_field((string) ($context['document_label'] ?? '')),
            'message'        => sanitize_text_field($message),
        ];

        array_unshift($log['entries'], $entry);
        $log['entries'] = array_slice($log['entries'], 0, self::MAX_ENTRIES);

        if (!empty($log['last_run']) && $entry['level'] === 'error') {
            $log['last_run']['failed'] = max(0, (int) ($log['last_run']['failed'] ?? 0)) + 1;
        }

        update_option(self::OPTION_NAME, $log, false);

        if ($createdAdHocRun) {
            self::endRun([
                'indexed' => 0,
                'skipped' => 0,
                'failed'  => $level === 'error' ? 1 : 0,
            ], __('A WordPress save indexing issue was logged.', 'typesense-search'));
        }
    }

    /**
     * @return array{last_run: array<string,mixed>|null, entries: array<int,array<string,mixed>>}
     */
    public static function getLog(): array
    {
        $log = get_option(self::OPTION_NAME, []);
        if (!is_array($log)) {
            $log = [];
        }

        return [
            'last_run' => isset($log['last_run']) && is_array($log['last_run']) ? $log['last_run'] : null,
            'entries'  => isset($log['entries']) && is_array($log['entries']) ? $log['entries'] : [],
        ];
    }

    public static function clear(): void
    {
        delete_option(self::OPTION_NAME);
        self::$currentRunId = null;
    }

    /**
     * @return array{message:string,strategy?:string,document_id?:string,document_label?:string}
     */
    private static function parseMessage(string $message): array
    {
        $context = [
            'message' => trim(wp_strip_all_tags($message)),
        ];

        if (preg_match('/^\[TypesenseSearch\](?:\[([^\]]+)\])?\s*(.*)$/', $message, $matches)) {
            if (!empty($matches[1])) {
                $context['strategy'] = $matches[1];
            }
            $context['message'] = trim($matches[2] ?: $message);
        }

        if (preg_match('/post\s+(\d+)/i', $message, $matches)) {
            $context['document_id'] = $matches[1];
            $context['document_label'] = self::getPostLabel((int) $matches[1]);
        } elseif (preg_match('/attachment\s+(\d+)/i', $message, $matches)) {
            $context['strategy'] = $context['strategy'] ?? 'pdf';
            $context['document_id'] = $matches[1];
            $context['document_label'] = self::getPostLabel((int) $matches[1]);
        } elseif (preg_match('/Document\s+"([^"]+)"/i', $message, $matches)) {
            $context['document_id'] = $matches[1];
            $context['document_label'] = $matches[1];
        }

        return $context;
    }

    private static function getPostLabel(int $postId): string
    {
        $title = function_exists('get_the_title') ? (string) get_the_title($postId) : '';

        return $title !== ''
            ? sprintf('%s (#%d)', $title, $postId)
            : sprintf('#%d', $postId);
    }

    /**
     * Avoid duplicate rows when the WordPress editor invokes the same indexing
     * path more than once during a single save.
     *
     * @param array<int,array<string,mixed>> $entries
     * @param array<string,mixed> $context
     */
    private static function isRecentDuplicate(array $entries, string $level, string $message, array $context): bool
    {
        $now = time();
        $strategy = sanitize_text_field((string) ($context['strategy'] ?? ''));
        $documentId = sanitize_text_field((string) ($context['document_id'] ?? ''));
        $cleanMessage = sanitize_text_field($message);

        foreach ($entries as $entry) {
            $timestamp = (int) ($entry['timestamp'] ?? 0);
            if ($timestamp > 0 && ($now - $timestamp) > self::DUPLICATE_WINDOW_SECONDS) {
                continue;
            }

            if (
                ($entry['level'] ?? '') === $level
                && (string) ($entry['strategy'] ?? '') === $strategy
                && (string) ($entry['document_id'] ?? '') === $documentId
                && (string) ($entry['message'] ?? '') === $cleanMessage
            ) {
                return true;
            }
        }

        return false;
    }
}
