<?php

namespace TypesenseSearch\CLI\Actions;

use TypesenseSearch\Admin\Settings;
use TypesenseSearch\App;
use TypesenseSearch\Helper\PdfToText;
use TypesenseSearch\Logger\IndexingLog;

/**
 * Handles the `wp typesense index` subcommand.
 *
 * Iterates over configured (or explicitly requested) post types, indexes each
 * published post via the strategy registry, and optionally indexes PDF
 * attachments and runs external strategies.
 *
 * @package TypesenseSearch\CLI\Actions
 */
class IndexAction
{
    /**
     * Run the index operation.
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assocArgs
     */
    public function handle(array $args, array $assocArgs): void
    {
        $isDryRun        = \WP_CLI\Utils\get_flag_value($assocArgs, 'dry-run', false);
        $rawBatch        = \WP_CLI\Utils\get_flag_value($assocArgs, 'batch-size', null);
        $batchSize       = $rawBatch === null ? -1 : max(1, (int) $rawBatch);
        $skipYes         = \WP_CLI\Utils\get_flag_value($assocArgs, 'yes', false);
        $sleepUs         = (int) \WP_CLI\Utils\get_flag_value($assocArgs, 'sleep', 0) * 1000;
        $includePdf      = (bool) \WP_CLI\Utils\get_flag_value($assocArgs, 'include-pdf', false);
        $includeExternal = (bool) \WP_CLI\Utils\get_flag_value($assocArgs, 'include-external', false);
        $onlyPdf         = (bool) \WP_CLI\Utils\get_flag_value($assocArgs, 'only-pdf', false);
        // false = not set | true = all external | string = specific identifier
        $onlyExternal    = \WP_CLI\Utils\get_flag_value($assocArgs, 'only-external', false);

        // ── Mutual exclusivity ───────────────────────────────────────────────
        if ($onlyPdf && $onlyExternal !== false) {
            \WP_CLI::error('--only-pdf and --only-external are mutually exclusive.');
        }
        if ($onlyPdf && \WP_CLI\Utils\get_flag_value($assocArgs, 'post-type', null) !== null) {
            \WP_CLI::error('--only-pdf cannot be combined with --post-type.');
        }
        if ($onlyExternal !== false && \WP_CLI\Utils\get_flag_value($assocArgs, 'post-type', null) !== null) {
            \WP_CLI::error('--only-external cannot be combined with --post-type.');
        }

        // ── Resolve post types ──────────────────────────────────────────────
        if (!$onlyPdf && $onlyExternal === false) {
            $enabledTypes = $this->resolvePostTypes($assocArgs);
            if (empty($enabledTypes)) {
                \WP_CLI::error(
                    'No post types are enabled for indexing. ' .
                    'Enable at least one on the Typesense Search settings page, ' .
                    'or pass --post-type=<type>.'
                );
            }
        } else {
            $enabledTypes = [];
        }
        $enabledObjects = Settings::getIndexablePostTypes();

        // ── Dry-run banner ──────────────────────────────────────────────────
        if ($isDryRun) {
            \WP_CLI::warning('DRY RUN — no documents will be written to Typesense.');
        }

        // ── Confirmation ────────────────────────────────────────────────────
        if ($onlyPdf) {
            $indexScope = 'PDF attachments only';
        } elseif ($onlyExternal !== false) {
            $extId      = is_string($onlyExternal) && $onlyExternal !== '' ? $onlyExternal : null;
            $indexScope = $extId !== null
                ? sprintf('external strategy "%s" only', $extId)
                : 'external strategies only';
        } else {
            $indexScope = sprintf(
                '%s%s%s',
                implode(', ', $enabledTypes),
                $includePdf ? ' and PDF attachments' : '',
                $includeExternal ? ' and external strategies' : ''
            );
        }

        if (!$isDryRun && !$skipYes) {
            \WP_CLI::confirm(
                sprintf(
                    'This will index: %s. Continue?',
                    $indexScope
                )
            );
        }

        // ── Count totals per post type for the progress bar ─────────────────
        $totalsByType = [];
        foreach ($enabledTypes as $postType) {
            $totalsByType[$postType] = (int) wp_count_posts($postType)->publish;
        }

        $grandTotal = array_sum($totalsByType);

        if ($grandTotal === 0 && !$onlyPdf && $onlyExternal === false) {
            \WP_CLI::success('No published posts found for the selected post types.');
            return;
        }

        if ($grandTotal > 0) {
            \WP_CLI::log(
                sprintf(
                    'Found %d published post(s) across %d post type(s).',
                    $grandTotal,
                    count($enabledTypes)
                )
            );
        }

        // ── Counters ────────────────────────────────────────────────────────
        $totalIndexed = 0;
        $totalSkipped = 0;
        $totalFailed  = 0;

        if (!$isDryRun) {
            IndexingLog::beginRun('cli', sprintf('WP-CLI index: %s', $indexScope));
        }

        // ── Iterate per post type ────────────────────────────────────────────
        foreach ($enabledTypes as $postType) {
            $typeLabel = isset($enabledObjects[$postType])
                ? $enabledObjects[$postType]->label
                : $postType;

            $typeTotal = $totalsByType[$postType];

            if ($typeTotal === 0) {
                \WP_CLI::log(sprintf('  Skipping "%s" — no published posts.', $typeLabel));
                continue;
            }

            \WP_CLI::log(sprintf(
                'Indexing post type: %s (%d post(s))',
                $typeLabel,
                $typeTotal
            ));

            $progress = \WP_CLI\Utils\make_progress_bar(
                sprintf('  [%s]', $postType),
                $typeTotal
            );

            $offset  = 0;
            $indexed = 0;
            $skipped = 0;
            $failed  = 0;

            do {
                $queryArgs = [
                    'post_type'              => $postType,
                    'post_status'            => 'publish',
                    'posts_per_page'         => $batchSize,
                    'orderby'                => 'ID',
                    'order'                  => 'ASC',
                    'suppress_filters'       => false,
                    'no_found_rows'          => true,
                    'update_post_meta_cache' => true,
                    'update_post_term_cache' => false,
                ];

                if ($batchSize !== -1) {
                    $queryArgs['offset'] = $offset;
                }

                $posts = get_posts($queryArgs);

                foreach ($posts as $post) {
                    try {
                        $strategy = App::getRegistry()->resolve($post);
                        if (!$strategy || !$strategy->shouldIndex($post)) {
                            $skipped++;
                            $progress->tick();
                            if ($sleepUs > 0) {
                                usleep($sleepUs);
                            }
                            continue;
                        }

                        if ($isDryRun) {
                            $indexed++;
                            $progress->tick();
                            if ($sleepUs > 0) {
                                usleep($sleepUs);
                            }
                            continue;
                        }

                        $result = $strategy->index($post);

                        if ($result) {
                            $indexed++;
                        } else {
                            $failed++;
                            \WP_CLI::warning(sprintf(
                                '    Failed to index post ID %d ("%s").',
                                $post->ID,
                                $post->post_title
                            ));
                        }
                    } catch (\Throwable $e) {
                        $failed++;
                        $this->logIndexingThrowable(
                            sprintf('post %d', $post->ID),
                            $e
                        );
                        \WP_CLI::warning(sprintf(
                            '    Failed to index post ID %d ("%s").',
                            $post->ID,
                            $post->post_title
                        ));
                    }

                    $progress->tick();
                    if ($sleepUs > 0) {
                        usleep($sleepUs);
                    }
                    $this->freeMemory();
                }

                $offset += $batchSize;
            } while ($batchSize !== -1 && count($posts) === $batchSize);

            $progress->finish();

            \WP_CLI::log(sprintf(
                '  Done: %d indexed, %d skipped, %d failed.',
                $indexed,
                $skipped,
                $failed
            ));

            $totalIndexed += $indexed;
            $totalSkipped += $skipped;
            $totalFailed  += $failed;
        }

        // ── PDF attachments ──────────────────────────────────────────────────
        if ($onlyPdf || $includePdf) {
            if (!PdfToText::isAvailable()) {
                \WP_CLI::warning('--include-pdf skipped: pdftotext binary is not available on this server.');
            } else {
                $pdfQueryArgs = [
                    'post_type'      => 'attachment',
                    'post_status'    => 'inherit',
                    'post_mime_type' => 'application/pdf',
                    'posts_per_page' => 1,
                    'no_found_rows'  => false,
                    'fields'         => 'ids',
                ];

                $countQuery = new \WP_Query($pdfQueryArgs);
                $pdfTotal   = $countQuery->found_posts;

                if ($pdfTotal === 0) {
                    \WP_CLI::log('  Skipping PDF attachments — none found in media library.');
                } else {
                    \WP_CLI::log(sprintf('Indexing PDF attachments (%d file(s))', $pdfTotal));
                    $progress = \WP_CLI\Utils\make_progress_bar('  [pdf]', $pdfTotal);

                    $pdfIndexed = 0;
                    $pdfSkipped = 0;
                    $pdfFailed  = 0;
                    $pdfOffset  = 0;

                    $pdfQueryArgs['fields']         = '';
                    $pdfQueryArgs['posts_per_page']  = $batchSize;

                    $pdfStrategy = App::getRegistry()->get('pdf');

                    do {
                        if ($batchSize !== -1) {
                            $pdfQueryArgs['offset'] = $pdfOffset;
                        }

                        $pdfs = get_posts($pdfQueryArgs);

                        foreach ($pdfs as $pdf) {
                            try {
                                if (!$pdfStrategy || $pdf->post_mime_type !== 'application/pdf') {
                                    $pdfSkipped++;
                                    $progress->tick();
                                    continue;
                                }

                                if ($isDryRun) {
                                    $pdfIndexed++;
                                    $progress->tick();
                                    continue;
                                }

                                $result = $pdfStrategy->index($pdf);
                                if ($result) {
                                    $pdfIndexed++;
                                } else {
                                    $pdfFailed++;
                                    \WP_CLI::warning(sprintf(
                                        '    Failed to index PDF attachment ID %d ("%s").',
                                        $pdf->ID,
                                        $pdf->post_title
                                    ));
                                }
                            } catch (\Throwable $e) {
                                $pdfFailed++;
                                $this->logIndexingThrowable(
                                    sprintf('PDF attachment %d', $pdf->ID),
                                    $e
                                );
                                \WP_CLI::warning(sprintf(
                                    '    Failed to index PDF attachment ID %d ("%s").',
                                    $pdf->ID,
                                    $pdf->post_title
                                ));
                            }

                            $progress->tick();
                            if ($sleepUs > 0) {
                                usleep($sleepUs);
                            }
                            $this->freeMemory();
                        }

                        $pdfOffset += $batchSize;
                    } while ($batchSize !== -1 && count($pdfs) === $batchSize);

                    $progress->finish();

                    \WP_CLI::log(sprintf(
                        '  Done: %d indexed, %d skipped, %d failed.',
                        $pdfIndexed,
                        $pdfSkipped,
                        $pdfFailed
                    ));

                    $totalIndexed += $pdfIndexed;
                    $totalSkipped += $pdfSkipped;
                    $totalFailed  += $pdfFailed;
                }
            }
        }

        // ── External strategies ────────────────────────────────────────────────
        if ($includeExternal || $onlyExternal !== false) {
            $extTargetId = is_string($onlyExternal) && $onlyExternal !== '' ? $onlyExternal : null;
            \WP_CLI::log('');
            \WP_CLI::log('Running external strategies…');
            [$extIndexed, $extFailed] = $this->runExternalSync($isDryRun, $extTargetId);
            $totalIndexed += $extIndexed;
            $totalFailed  += $extFailed;
        }

        // ── Summary ─────────────────────────────────────────────────────────
        \WP_CLI::log('');
        $dryLabel = $isDryRun ? ' (dry run)' : '';

        if ($totalFailed > 0) {
            if (!$isDryRun) {
                IndexingLog::endRun([
                    'indexed' => $totalIndexed,
                    'skipped' => $totalSkipped,
                    'failed'  => $totalFailed,
                ], sprintf(
                    'Finished%s: %d indexed, %d skipped, %d failed.',
                    $dryLabel,
                    $totalIndexed,
                    $totalSkipped,
                    $totalFailed
                ));
            }
            \WP_CLI::warning(sprintf(
                'Finished%s: %d indexed, %d skipped, %d failed.',
                $dryLabel,
                $totalIndexed,
                $totalSkipped,
                $totalFailed
            ));
        } else {
            if (!$isDryRun) {
                IndexingLog::endRun([
                    'indexed' => $totalIndexed,
                    'skipped' => $totalSkipped,
                    'failed'  => $totalFailed,
                ], sprintf(
                    'Finished%s: %d indexed, %d skipped, %d failed.',
                    $dryLabel,
                    $totalIndexed,
                    $totalSkipped,
                    $totalFailed
                ));
            }
            \WP_CLI::success(sprintf(
                'Finished%s: %d indexed, %d skipped, %d failed.',
                $dryLabel,
                $totalIndexed,
                $totalSkipped,
                $totalFailed
            ));
        }
    }

    /**
     * Resolve which post types to index.
     *
     * Uses --post-type if supplied, otherwise falls back to the post types
     * enabled in the plugin settings. Warns and removes any post types that
     * are not enabled in settings (unless explicitly passed via --post-type).
     *
     * @param array<string,string> $assocArgs
     * @return string[]
     */
    public function resolvePostTypes(array $assocArgs): array
    {
        $enabledInSettings = array_keys(Settings::getIndexablePostTypes());
        $settingsEnabled   = array_filter(
            $enabledInSettings,
            fn(string $type) => Settings::isPostTypeEnabled($type)
        );

        $rawArg = \WP_CLI\Utils\get_flag_value($assocArgs, 'post-type', null);

        if ($rawArg === null) {
            return array_values($settingsEnabled);
        }

        // Explicit --post-type supplied: validate each against indexable types.
        $requested = array_map('trim', explode(',', (string) $rawArg));
        $resolved  = [];

        foreach ($requested as $type) {
            if (!post_type_exists($type)) {
                \WP_CLI::warning(sprintf('Post type "%s" does not exist — skipping.', $type));
                continue;
            }

            if (!Settings::isPostTypeEnabled($type)) {
                \WP_CLI::warning(sprintf(
                    'Post type "%s" is not enabled in Typesense Search settings — skipping. ' .
                    'Enable it on the settings page to include it.',
                    $type
                ));
                continue;
            }

            $resolved[] = $type;
        }

        return $resolved;
    }

    /**
     * Run all registered external strategies and return [totalIndexed, totalFailed].
     *
     * Used by commands that support --include-external / --only-external. Prints
     * per-strategy log lines but does not print a final summary (the caller does that).
     *
     * @param bool        $isDryRun
     * @param string|null $targetId  When non-null, only the strategy with this
     *                               identifier is run.
     * @return array{int, int}
     */
    private function runExternalSync(bool $isDryRun, ?string $targetId = null): array
    {
        $strategies = App::getRegistry()->allExternal();

        if (empty($strategies)) {
            \WP_CLI::log('  No external strategies registered — skipping.');
            return [0, 0];
        }

        if ($targetId !== null) {
            if (!isset($strategies[$targetId])) {
                $available = implode(', ', array_keys($strategies));
                \WP_CLI::error(sprintf(
                    'Unknown external strategy "%s". Available: %s',
                    $targetId,
                    $available ?: 'none registered'
                ));
                return [0, 0]; // unreachable after error
            }
            $strategies = [$targetId => $strategies[$targetId]];
        }

        $indexed = 0;
        $failed  = 0;

        foreach ($strategies as $id => $strategy) {
            \WP_CLI::log(sprintf('  Syncing "%s"…', $id));

            if ($isDryRun) {
                \WP_CLI::log(sprintf('    [dry run] Would sync external strategy "%s".', $id));
                continue;
            }

            try {
                $count    = $strategy->syncAll();
                $indexed += $count;
                \WP_CLI::log(sprintf('    ✓ %d document(s) indexed.', $count));
            } catch (\Throwable $e) {
                $failed++;
                IndexingLog::recordIssue('error', $e->getMessage(), [
                    'strategy'       => $id,
                    'document_label' => $id,
                ]);
                \WP_CLI::warning(sprintf('    ✗ "%s" failed: %s', $id, $e->getMessage()));
            }
        }

        return [$indexed, $failed];
    }

    /**
     * Write unexpected per-document indexing failures to PHP's error log so
     * the batch can continue while preserving enough context to debug later.
     */
    private function logIndexingThrowable(string $documentLabel, \Throwable $e): void
    {
        error_log(sprintf(
            '[TypesenseSearch][cli] Document for %s could not be indexed: %s',
            $documentLabel,
            $e->getMessage()
        ));

        IndexingLog::recordIssue('error', $e->getMessage(), [
            'strategy'       => 'cli',
            'document_label' => $documentLabel,
        ]);
    }

    /**
     * Free object cache and reset post data between batches to keep memory
     * usage stable during large indexing runs.
     */
    private function freeMemory(): void
    {
        wp_cache_flush_runtime();
    }
}
