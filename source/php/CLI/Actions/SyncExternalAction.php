<?php

namespace TypesenseSearch\CLI\Actions;

use TypesenseSearch\App;
use TypesenseSearch\Logger\IndexingLog;

/**
 * Handles the `wp typesense sync-external` and `wp typesense list-external` subcommands.
 *
 * External strategies are registered via the
 * 'Municipio/TypesenseSearch/RegisterStrategies' action and pull content from
 * outside WordPress (e.g. APIs). This class provides the CLI surface for
 * running and inspecting them.
 *
 * @package TypesenseSearch\CLI\Actions
 */
class SyncExternalAction
{
    /**
     * Sync one or all external indexing strategies.
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assocArgs
     */
    public function syncExternal(array $args, array $assocArgs): void
    {
        $isDryRun  = (bool) \WP_CLI\Utils\get_flag_value($assocArgs, 'dry-run', false);
        $skipYes   = (bool) \WP_CLI\Utils\get_flag_value($assocArgs, 'yes', false);
        $targetId  = $args[0] ?? null;

        $registry = App::getRegistry();

        $strategies = $registry->allExternal();

        if (empty($strategies)) {
            \WP_CLI::warning(
                'No external strategies are registered. ' .
                'Third-party plugins register strategies via the ' .
                '\'Municipio/TypesenseSearch/RegisterStrategies\' action.'
            );
            return;
        }

        // Filter to the requested identifier when one is given.
        if ($targetId !== null) {
            if (!isset($strategies[$targetId])) {
                $available = implode(', ', array_keys($strategies));
                \WP_CLI::error(sprintf(
                    'Unknown external strategy "%s". Available: %s',
                    $targetId,
                    $available
                ));
                return;
            }

            $strategies = [$targetId => $strategies[$targetId]];
        }

        // ── Dry-run ──────────────────────────────────────────────────────────
        if ($isDryRun) {
            \WP_CLI::warning('DRY RUN — no documents will be fetched or written.');
            \WP_CLI::log('');
            \WP_CLI::log(sprintf('%d external strategy/strategies registered:', count($strategies)));

            foreach ($strategies as $id => $strategy) {
                \WP_CLI::log(sprintf('  • %s', $id));
            }

            \WP_CLI::log('');
            \WP_CLI::success('Dry run complete. Run without --dry-run to sync.');
            return;
        }

        // ── Confirmation ─────────────────────────────────────────────────────
        if (!$skipYes) {
            \WP_CLI::confirm(sprintf(
                'This will sync %d external strategy/strategies: %s. Continue?',
                count($strategies),
                implode(', ', array_keys($strategies))
            ));
        }

        // ── Run each strategy ─────────────────────────────────────────────────
        $totalIndexed = 0;
        $totalFailed  = 0;

        IndexingLog::beginRun('cli', sprintf(
            'WP-CLI sync-external: %s',
            implode(', ', array_keys($strategies))
        ));

        foreach ($strategies as $id => $strategy) {
            \WP_CLI::log(sprintf('Syncing "%s"…', $id));

            try {
                $count = $strategy->syncAll();
                \WP_CLI::log(sprintf('  ✓ %d document(s) indexed.', $count));
                $totalIndexed += $count;
            } catch (\Throwable $e) {
                \WP_CLI::warning(sprintf('  ✗ "%s" failed: %s', $id, $e->getMessage()));
                IndexingLog::recordIssue('error', $e->getMessage(), [
                    'strategy'       => $id,
                    'document_label' => $id,
                ]);
                $totalFailed++;
            }
        }

        \WP_CLI::log('');
        IndexingLog::endRun([
            'indexed' => $totalIndexed,
            'skipped' => 0,
            'failed'  => $totalFailed,
        ], sprintf(
            'External sync finished: %d indexed, %d strategy/strategies failed.',
            $totalIndexed,
            $totalFailed
        ));

        if ($totalFailed === 0) {
            \WP_CLI::success(sprintf(
                'Done. %d document(s) indexed across %d strategy/strategies.',
                $totalIndexed,
                count($strategies)
            ));
        } else {
            \WP_CLI::warning(sprintf(
                'Finished with errors. %d indexed, %d strategy/strategies failed.',
                $totalIndexed,
                $totalFailed
            ));
        }
    }

    /**
     * List all registered external indexing strategies.
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assocArgs
     */
    public function listExternal(array $args, array $assocArgs): void
    {
        $strategies = App::getRegistry()->allExternal();

        if (empty($strategies)) {
            \WP_CLI::warning(
                'No external strategies are registered. ' .
                'Third-party plugins register strategies via the ' .
                '\'Municipio/TypesenseSearch/RegisterStrategies\' action.'
            );
            return;
        }

        \WP_CLI::log(sprintf('%d external strategy/strategies registered:', count($strategies)));
        \WP_CLI::log('');

        foreach ($strategies as $id => $strategy) {
            \WP_CLI::log(sprintf('  • %s', $id));
        }

        \WP_CLI::log('');
        \WP_CLI::success(
            'Use `wp typesense sync-external <identifier>` to sync, ' .
            'or `wp typesense clear --only-external=<identifier>` to remove documents.'
        );
    }
}
