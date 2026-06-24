<?php

namespace TypesenseSearch\CLI;

use TypesenseSearch\Admin\Settings;
use TypesenseSearch\App;
use TypesenseSearch\Helper\PdfToText;
use TypesenseSearch\Logger\IndexingLog;
use TypesenseSearch\SearchStatistics\Repository as SearchStatisticsRepository;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Typesense\ClientFactory;

/**
 * WP-CLI command for managing the Typesense search index.
 *
 * ## EXAMPLES
 *
 *   # Index all enabled post types.
 *   wp typesense index
 *
 *   # Preview what would be indexed without making changes.
 *   wp typesense index --dry-run
 *
 *   # Index only pages.
 *   wp typesense index --post-type=page
 *
 *   # Index posts with a custom batch size and skip confirmation.
 *   wp typesense index --post-type=post --batch-size=50 --yes
 *
 * @package TypesenseSearch\CLI
 */
class IndexCommand
{
    public function __construct(
        private SettingsRepository $settings,
        private SearchStatisticsRepository $searchStatistics
    ) {
    }

    /**
     * Remove search-statistics rows that are older than the configured
     * retention period.
     *
     * ## OPTIONS
     *
     * [--days=<days>]
     * : Override the retention period configured in Advanced settings.
     *
     * ## EXAMPLES
     *
     *   # Prune according to the configured retention period.
     *   wp typesense prune-search-statistics
     *
     *   # Prune entries older than 30 days.
     *   wp typesense prune-search-statistics --days=30
     *
     * @subcommand prune-search-statistics
     * @when after_wp_load
     *
     * @param array<int,string> $args Positional arguments (unused).
     * @param array<string,string> $assocArgs Named arguments.
     */
    public function pruneSearchStatistics(array $args, array $assocArgs): void
    {
        $days = \WP_CLI\Utils\get_flag_value($assocArgs, 'days', $this->settings->getSearchStatisticsRetentionDays());
        $days = max(1, (int) $days);
        $deleted = $this->searchStatistics->prune($days);

        \WP_CLI::success(sprintf(
            'Deleted %d search statistic%s older than %d day%s.',
            $deleted,
            $deleted === 1 ? '' : 's',
            $days,
            $days === 1 ? '' : 's'
        ));
    }

    /**
     * Add sample search-log entries for testing pagination and filters.
     *
     * ## OPTIONS
     *
     * [--count=<number>]
     * : Number of sample entries to add. Defaults to 100.
     *
     * [--repeat-percent=<percentage>]
     * : Percentage of entries that reuse one of the sample terms. Defaults to
     *   70. The remaining entries use unique suffixed terms. Values are
     *   clamped between 0 and 100.
     *
     * ## EXAMPLES
     *
     *   # Add enough entries to exercise the second page of the log.
     *   wp typesense populate-search-log --count=50
     *
     *   # Add 100 entries, 80% of which reuse the sample terms.
     *   wp typesense populate-search-log --count=100 --repeat-percent=80
     *
     * @subcommand populate-search-log
     * @when after_wp_load
     *
     * @param array<int,string> $args Positional arguments (unused).
     * @param array<string,string> $assocArgs Named arguments.
     */
    public function populateSearchLog(array $args, array $assocArgs): void
    {
        $count = min(10000, max(1, (int) \WP_CLI\Utils\get_flag_value($assocArgs, 'count', 100)));
        $repeatPercent = min(100, max(0, (int) \WP_CLI\Utils\get_flag_value($assocArgs, 'repeat-percent', 70)));
        $terms = [
            'bygglov',
            'förskola',
            'parkering',
            'återvinning',
            'fritidsaktiviteter',
            'bostadsanpassning',
            'bibliotek',
            'lediga jobb',
            'serveringstillstånd',
            'snöröjning',
        ];
        $created = 0;

        for ($index = 0; $index < $count; $index++) {
            $baseTerm = $terms[$index % count($terms)];
            $term = ($index % 100) < $repeatPercent
                ? $baseTerm
                : $baseTerm . ' test ' . $index;
            $sessionId = str_replace('-', '', wp_generate_uuid4());
            $found = $index % 7 === 0 ? 0 : (($index % 200) + 1);
            $surface = $index % 3 === 0 ? 'quick' : 'regular';

            if ($this->searchStatistics->record($term, $found, $surface, $sessionId)) {
                $created++;
            }
        }

        \WP_CLI::success(sprintf(
            'Added %d sample search-log entr%s (%d%% repeated terms).',
            $created,
            $created === 1 ? 'y' : 'ies',
            $repeatPercent
        ));
    }

    /**
     * Index all published posts for the post types enabled in plugin settings.
     *
     * ## OPTIONS
     *
     * [--post-type=<type>]
     * : Comma-separated list of post types to index. Defaults to all post
     *   types enabled in the Typesense Search settings page.
     *
     * [--batch-size=<number>]
     * : Number of posts to query per database batch. Defaults to all posts
     *   in a single query. Use this to limit memory usage on large sites.
     *
     * [--dry-run]
     * : Preview what would be indexed without writing anything to Typesense.
     *
     * [--skip-excluded]
     * : Also skip posts that have the "exclude from search" meta flag set
     *   (this is already enforced by the strategy's shouldIndex() — use this
     *   flag to make it explicit in the output).
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * [--include-pdf]
     * : Also index PDF files from the media library using pdftotext. Requires
     *   pdftotext to be available on the server. Operates independently of
     *   the "Index PDF files" toggle on the settings page.
     *
     * [--sleep=<milliseconds>]
     * : Sleep for the given number of milliseconds after each post. Useful
     *   for visually verifying the progress bar during development/testing.
     *   Example: --sleep=200
     *
     * [--include-external]
     * : After indexing posts (and optionally PDFs), also run all registered
     *   external strategies. Equivalent to appending `wp typesense sync-external`.
     *
     * [--only-pdf]
     * : Index ONLY PDF attachments from the media library; skips the post-type
     *   loop and the external-strategies step entirely. Requires pdftotext to be
     *   available on the server. Cannot be combined with --post-type or
     *   --only-external.
     *
     * [--only-external[=<identifier>]]
     * : Index ONLY documents from external strategies; skips the post-type loop
     *   and the PDF step entirely. When given without a value, all registered
     *   external strategies are run. When given with an identifier (e.g.
     *   --only-external=pitea-eservice), only that strategy is run. Cannot be
     *   combined with --post-type or --only-pdf.
     *
     * ## EXAMPLES
     *
     *   wp typesense index
     *   wp typesense index --dry-run
     *   wp typesense index --post-type=post,page
     *   wp typesense index --batch-size=50 --yes
     *   wp typesense index --include-pdf
     *   wp typesense index --dry-run --sleep=200
     *   wp typesense index --include-external --yes
     *   wp typesense index --only-pdf --yes
     *   wp typesense index --only-external --yes
     *   wp typesense index --only-external=pitea-eservice --yes
     *
     * @subcommand index
     * @when after_wp_load
     *
     * @param array<int,string>    $args       Positional arguments (unused).
     * @param array<string,string> $assocArgs  Named arguments.
     */
    public function index(array $args, array $assocArgs): void
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

    // ─────────────────────────────────────────────────────────────────────────
    // Rebuild command
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Drop the Typesense collection, recreate it from the plugin schema, and
     * optionally re-index all published posts in one operation.
     *
     * This is the recommended way to apply schema changes (e.g. after editing
     * the `Municipio/TypesenseSearch/Collection/getSchema` filter). It combines
     * three steps: drop → create → index.
     *
     * Use `--skip-index` when you only want to reset the schema without
     * immediately re-populating it (you can run `wp typesense index` later).
     *
     * ## OPTIONS
     *
     * [--post-type=<type>]
     * : Comma-separated list of post types to re-index after the schema reset.
     *   Defaults to all post types enabled in the Typesense Search settings.
     *   Only relevant when --skip-index is not set.
     *
     * [--batch-size=<number>]
     * : Number of posts to query per database batch during re-indexing.
     *   Defaults to all posts in a single query.
     *
     * [--skip-index]
     * : Drop and recreate the schema only; do not re-index any posts.
     *
     * [--dry-run]
     * : Preview what would happen without writing anything to Typesense.
     *   Reports whether the collection exists, what schema would be created,
     *   and how many posts would be re-indexed.
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * [--include-pdf]
     * : Also index PDF files from the media library using pdftotext after the
     *   schema has been recreated. Requires pdftotext to be available on the
     *   server. Operates independently of the "Index PDF files" toggle on the
     *   settings page.
     *
     * [--sleep=<milliseconds>]
     * : Sleep for the given number of milliseconds after each post during
     *   re-indexing. Useful for pacing during development/testing.
     *
     * [--include-external]
     * : After re-indexing posts (and optionally PDFs), also run all registered
     *   external strategies. Forwarded transparently to the internal index step.
     *
     * ## EXAMPLES
     *
     *   # Full rebuild: drop schema, recreate, re-index everything.
     *   wp typesense rebuild
     *
     *   # Preview without making any changes.
     *   wp typesense rebuild --dry-run
     *
     *   # Reset schema only, re-index manually later.
     *   wp typesense rebuild --skip-index --yes
     *
     *   # Rebuild and re-index only pages, skip confirmation.
     *   wp typesense rebuild --post-type=page --yes
     *
     *   # Full rebuild including PDF attachments.
     *   wp typesense rebuild --include-pdf --yes
     *
     * @subcommand rebuild
     * @when after_wp_load
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assocArgs
     */
    public function rebuild(array $args, array $assocArgs): void
    {
        $isDryRun        = \WP_CLI\Utils\get_flag_value($assocArgs, 'dry-run', false);
        $skipIndex       = \WP_CLI\Utils\get_flag_value($assocArgs, 'skip-index', false);
        $skipYes         = \WP_CLI\Utils\get_flag_value($assocArgs, 'yes', false);
        $includePdf      = (bool) \WP_CLI\Utils\get_flag_value($assocArgs, 'include-pdf', false);
        $includeExternal = (bool) \WP_CLI\Utils\get_flag_value($assocArgs, 'include-external', false);

        // ── Client + collection name ─────────────────────────────────────────
        $client         = ClientFactory::fromOptions();
        $collectionName = (string) get_option(Settings::OPTION_INDEX_NAME, '');

        if ($client === null || $collectionName === '') {
            \WP_CLI::error('Typesense connection is not configured. Check the plugin settings page.');
            return;
        }

        // ── Dry-run banner ───────────────────────────────────────────────────
        if ($isDryRun) {
            \WP_CLI::warning('DRY RUN — no changes will be made to Typesense.');
        }

        // ── Confirmation ─────────────────────────────────────────────────────
        $actionLabel = $skipIndex
            ? sprintf('drop and recreate the schema for collection "%s"', $collectionName)
            : sprintf('drop, recreate, and re-index collection "%s"', $collectionName);

        if (!$isDryRun && !$skipYes) {
            \WP_CLI::confirm(sprintf(
                'This will permanently %s. All existing documents will be lost. Continue?',
                $actionLabel
            ));
        }

        // ── Inspect current state ────────────────────────────────────────────
        $collectionExists = \TypesenseSearch\Typesense\Collection::exists($client, $collectionName);

        if ($isDryRun) {
            \WP_CLI::log(sprintf(
                '  Collection "%s" %s.',
                $collectionName,
                $collectionExists ? 'exists and would be dropped' : 'does not exist (nothing to drop)'
            ));
            \WP_CLI::log(sprintf(
                '  Schema would be recreated using %s.',
                \TypesenseSearch\Typesense\Collection::FILTER_SCHEMA
            ));

            if (!$skipIndex) {
                $enabledTypes = $this->resolvePostTypes($assocArgs);
                if (empty($enabledTypes)) {
                    \WP_CLI::log('  No post types enabled — re-indexing step would be skipped.');
                } else {
                    $total = 0;
                    foreach ($enabledTypes as $pt) {
                        $total += (int) wp_count_posts($pt)->publish;
                    }
                    \WP_CLI::log(sprintf(
                        '  Would re-index %d published post(s) across type(s): %s%s%s.',
                        $total,
                        implode(', ', $enabledTypes),
                        $includePdf ? ' and PDF attachments' : '',
                        $includeExternal ? ' and external strategies' : ''
                    ));
                }
            } else {
                \WP_CLI::log('  --skip-index set: re-indexing step would be skipped.');
            }

            \WP_CLI::success('Dry run complete. No changes were made.');
            return;
        }

        // ── Drop ─────────────────────────────────────────────────────────────
        if ($collectionExists) {
            \WP_CLI::log(sprintf('Dropping collection "%s"…', $collectionName));
            try {
                \TypesenseSearch\Typesense\Collection::drop($client, $collectionName);
                \WP_CLI::log('  Done.');
            } catch (\Exception $e) {
                \WP_CLI::error(sprintf('Failed to drop collection: %s', $e->getMessage()));
                return;
            }
        } else {
            \WP_CLI::log(sprintf(
                'Collection "%s" does not exist — skipping drop step.',
                $collectionName
            ));
        }

        // ── Create ───────────────────────────────────────────────────────────
        \WP_CLI::log(sprintf('Creating collection "%s" from schema…', $collectionName));
        try {
            \TypesenseSearch\Typesense\Collection::create($client, $collectionName);
            \WP_CLI::log('  Done.');
        } catch (\Exception $e) {
            \WP_CLI::error(sprintf('Failed to create collection: %s', $e->getMessage()));
            return;
        }

        // ── Re-index ─────────────────────────────────────────────────────────
        if ($skipIndex) {
            \WP_CLI::log('');
            \WP_CLI::success(sprintf(
                'Schema for collection "%s" has been reset. Run `wp typesense index` to populate it.',
                $collectionName
            ));
            return;
        }

        \WP_CLI::log('');
        \WP_CLI::log('Starting re-indexing…');
        $forwardArgs = array_merge($assocArgs, ['yes' => true]);
        if ($includePdf) {
            $forwardArgs['include-pdf'] = true;
        }
        if ($includeExternal) {
            $forwardArgs['include-external'] = true;
        }
        $this->index($args, $forwardArgs);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Clear command
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Remove indexed documents from the Typesense collection.
     *
     * Deletes are executed as a single bulk request per post type (or one
     * request for the entire collection), so the operation is fast even for
     * large collections. Use --dry-run to preview the affected document count
     * without writing anything.
     *
     * ## OPTIONS
     *
     * [--post-type=<type>]
     * : Comma-separated list of post types to clear. Defaults to all post
     *   types that are enabled in the Typesense Search settings page.
     *   Pass "all" to clear every document in the collection regardless of
     *   settings (e.g. to remove stale types).
     *
     * [--dry-run]
     * : Count matching documents and print a summary without deleting anything.
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * [--include-pdf]
     * : Also clear PDF attachment documents (type=attachment) from the index.
     *   Combined with post-type clearing, not exclusive.
     *
     * [--include-external]
     * : Also clear all documents belonging to registered external strategies,
     *   matched by their type identifier. Ignored when --post-type=all is used
     *   since that already removes every document.
     *
     * [--only-pdf]
     * : Clear ONLY PDF attachment documents (type=attachment) from the index;
     *   skips the post-type loop entirely. Cannot be combined with
     *   --post-type, --only-external, or --post-type=all.
     *
     * [--only-external[=<identifier>]]
     * : Clear ONLY documents belonging to external strategies; skips the
     *   post-type loop and the PDF step. When given without a value, all
     *   registered external strategies are targeted. When given with an
     *   identifier (e.g. --only-external=pitea-eservice), only that
     *   strategy's documents are removed. Cannot be combined with
     *   --post-type or --only-pdf.
     *
     * [--sleep=<milliseconds>]
     * : Sleep for the given number of milliseconds between post-type operations.
     *   Useful when verifying output during development.
     *
     * ## EXAMPLES
     *
     *   # Clear all enabled post types.
     *   wp typesense clear
     *
     *   # Preview without deleting.
     *   wp typesense clear --dry-run
     *
     *   # Remove only pages.
     *   wp typesense clear --post-type=page
     *
     *   # Remove every document regardless of settings.
     *   wp typesense clear --post-type=all --yes
     *
     *   # Clear only PDF attachments (no post types, no external).
     *   wp typesense clear --only-pdf --yes
     *
     *   # Clear only external strategy documents (all strategies).
     *   wp typesense clear --only-external --yes
     *
     *   # Clear only one external strategy's documents.
     *   wp typesense clear --only-external=pitea-eservice --yes
     *
     * @subcommand clear
     * @when after_wp_load
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assocArgs
     */
    public function clear(array $args, array $assocArgs): void
    {
        $isDryRun        = \WP_CLI\Utils\get_flag_value($assocArgs, 'dry-run', false);
        $skipYes         = \WP_CLI\Utils\get_flag_value($assocArgs, 'yes', false);
        $sleepUs         = (int) \WP_CLI\Utils\get_flag_value($assocArgs, 'sleep', 0) * 1000;
        $rawType         = \WP_CLI\Utils\get_flag_value($assocArgs, 'post-type', null);
        $clearAll        = $rawType === 'all';
        $includePdf      = (bool) \WP_CLI\Utils\get_flag_value($assocArgs, 'include-pdf', false);
        $includeExternal = (bool) \WP_CLI\Utils\get_flag_value($assocArgs, 'include-external', false);
        $onlyPdf         = (bool) \WP_CLI\Utils\get_flag_value($assocArgs, 'only-pdf', false);
        // false = not set | true = all external | string = specific identifier
        $onlyExternal    = \WP_CLI\Utils\get_flag_value($assocArgs, 'only-external', false);

        // ── Mutual exclusivity ───────────────────────────────────────────────
        if ($onlyPdf && $onlyExternal !== false) {
            \WP_CLI::error('--only-pdf and --only-external are mutually exclusive.');
        }
        if ($onlyPdf && ($clearAll || $rawType !== null)) {
            \WP_CLI::error('--only-pdf cannot be combined with --post-type.');
        }
        if ($onlyExternal !== false && ($clearAll || $rawType !== null)) {
            \WP_CLI::error('--only-external cannot be combined with --post-type.');
        }

        // ── Client + collection name ─────────────────────────────────────────
        $client         = ClientFactory::fromOptions();
        $collectionName = (string) get_option(Settings::OPTION_INDEX_NAME, '');

        if ($client === null || $collectionName === '') {
            \WP_CLI::error('Typesense connection is not configured. Check the plugin settings page.');
        }

        // ── Dry-run banner ───────────────────────────────────────────────────
        if ($isDryRun) {
            \WP_CLI::warning('DRY RUN — no documents will be deleted from Typesense.');
        }

        // ── Post types to clear ──────────────────────────────────────────────
        // When --only-pdf or --only-external is set the post-type loop is
        // skipped entirely, so we can leave $postTypes empty.
        if ($clearAll || $onlyPdf || $onlyExternal !== false) {
            $postTypes = [];
        } else {
            $postTypes = $this->resolvePostTypesForClear($rawType);
            if (empty($postTypes)) {
                \WP_CLI::error(
                    'No post types resolved. Enable types on the settings page, ' .
                    'pass --post-type=<type>, or use --post-type=all.'
                );
            }
        }

        // ── Confirmation ─────────────────────────────────────────────────────
        if ($onlyPdf) {
            $scopeLabel = 'PDF attachment documents only';
        } elseif ($onlyExternal !== false) {
            $extId = is_string($onlyExternal) && $onlyExternal !== '' ? $onlyExternal : null;
            $scopeLabel = $extId !== null
                ? sprintf('documents for external strategy "%s"', $extId)
                : 'all external strategy documents';
        } elseif ($clearAll) {
            $scopeLabel = $includePdf
                ? 'ALL documents (including PDF attachments)'
                : 'ALL documents';
        } else {
            $externalSuffix = $includeExternal ? ' and external strategy documents' : '';
            $scopeLabel = sprintf(
                'documents for post type(s): %s%s%s',
                implode(', ', $postTypes),
                $includePdf ? ' and PDF attachments' : '',
                $externalSuffix
            );
        }

        if (!$isDryRun && !$skipYes) {
            \WP_CLI::confirm(sprintf(
                'This will permanently delete %s from collection "%s". Continue?',
                $scopeLabel,
                $collectionName
            ));
        }

        // ── Execute per operation ─────────────────────────────────────────────
        // $operations is a list of post-type strings, or [null] for "clear all".
        // Empty when --only-pdf or --only-external is set — loop won't execute.
        $operations     = $clearAll ? [null] : $postTypes;
        $enabledObjects = Settings::getIndexablePostTypes();
        $totalDeleted   = 0;

        foreach ($operations as $postType) {
            if ($postType !== null) {
                $label    = isset($enabledObjects[$postType])
                    ? sprintf('%s (%s)', $enabledObjects[$postType]->label, $postType)
                    : $postType;
                $filterBy = sprintf('type:=%s', $postType);
            } else {
                $label    = 'all post types';
                $filterBy = 'id:!= 0'; // WP post IDs are always > 0
            }

            // Count matching documents (used in both dry-run and live modes).
            try {
                $search = $client->collections[$collectionName]->documents->search([
                    'q'              => '*',
                    'query_by'       => 'title',
                    'filter_by'      => $filterBy,
                    'per_page'       => 0,
                    'include_fields' => 'id',
                ]);
                $count = (int) ($search['found'] ?? 0);
            } catch (\Exception $e) {
                \WP_CLI::warning(sprintf(
                    'Could not count documents for %s: %s',
                    $label,
                    $e->getMessage()
                ));
                $count = 0;
            }

            if ($isDryRun) {
                \WP_CLI::log(sprintf('  Would delete %d document(s) for %s.', $count, $label));
            } elseif ($count === 0) {
                \WP_CLI::log(sprintf('  No documents found for %s — skipping.', $label));
            } else {
                try {
                    $result = $client->collections[$collectionName]->documents->delete([
                        'filter_by' => $filterBy,
                    ]);
                    $deleted      = (int) ($result['num_deleted'] ?? 0);
                    $totalDeleted += $deleted;
                    \WP_CLI::log(sprintf('  Deleted %d document(s) for %s.', $deleted, $label));
                } catch (\Exception $e) {
                    \WP_CLI::warning(sprintf('Failed to clear %s: %s', $label, $e->getMessage()));
                }
            }

            if ($sleepUs > 0) {
                usleep($sleepUs);
            }
        }

        // ── PDF attachments ──────────────────────────────────────────────────
        if (($onlyPdf || $includePdf) && !$clearAll) {
            $pdfFilterBy = 'type:=attachment';

            try {
                $search = $client->collections[$collectionName]->documents->search([
                    'q'              => '*',
                    'query_by'       => 'title',
                    'filter_by'      => $pdfFilterBy,
                    'per_page'       => 0,
                    'include_fields' => 'id',
                ]);
                $pdfCount = (int) ($search['found'] ?? 0);
            } catch (\Exception $e) {
                \WP_CLI::warning(sprintf('Could not count PDF documents: %s', $e->getMessage()));
                $pdfCount = 0;
            }

            if ($isDryRun) {
                \WP_CLI::log(sprintf('  Would delete %d PDF document(s) (type=attachment).', $pdfCount));
            } elseif ($pdfCount === 0) {
                \WP_CLI::log('  No PDF documents found in index — skipping.');
            } else {
                try {
                    $result = $client->collections[$collectionName]->documents->delete([
                        'filter_by' => $pdfFilterBy,
                    ]);
                    $deleted       = (int) ($result['num_deleted'] ?? 0);
                    $totalDeleted += $deleted;
                    \WP_CLI::log(sprintf('  Deleted %d PDF document(s).', $deleted));
                } catch (\Exception $e) {
                    \WP_CLI::warning(sprintf('Failed to clear PDF documents: %s', $e->getMessage()));
                }
            }
        }

        // ── External strategies ────────────────────────────────────────────────
        if (($onlyExternal !== false || $includeExternal) && !$clearAll) {
            $extStrategies = App::getRegistry()->allExternal();

            // Filter to a specific strategy when --only-external=<identifier>.
            if (is_string($onlyExternal) && $onlyExternal !== '') {
                if (!isset($extStrategies[$onlyExternal])) {
                    $available = implode(', ', array_keys($extStrategies));
                    \WP_CLI::error(sprintf(
                        'Unknown external strategy "%s". Available: %s',
                        $onlyExternal,
                        $available ?: 'none registered'
                    ));
                }
                $extStrategies = [$onlyExternal => $extStrategies[$onlyExternal]];
            }

            if (empty($extStrategies)) {
                \WP_CLI::log('  No external strategies registered — skipping.');
            } else {
                foreach ($extStrategies as $extId => $strategy) {
                    $extFilter = sprintf('type:=%s', $strategy->getIdentifier());

                    try {
                        $extSearch = $client->collections[$collectionName]->documents->search([
                            'q'              => '*',
                            'query_by'       => 'title',
                            'filter_by'      => $extFilter,
                            'per_page'       => 0,
                            'include_fields' => 'id',
                        ]);
                        $extCount = (int) ($extSearch['found'] ?? 0);
                    } catch (\Exception $e) {
                        \WP_CLI::warning(sprintf(
                            'Could not count documents for external strategy "%s": %s',
                            $extId,
                            $e->getMessage()
                        ));
                        continue;
                    }

                    if ($isDryRun) {
                        \WP_CLI::log(sprintf(
                            '  Would delete %d document(s) for external strategy "%s".',
                            $extCount,
                            $extId
                        ));
                    } elseif ($extCount === 0) {
                        \WP_CLI::log(sprintf(
                            '  No documents found for external strategy "%s" — skipping.',
                            $extId
                        ));
                    } else {
                        try {
                            $extResult     = $client->collections[$collectionName]->documents->delete([
                                'filter_by' => $extFilter,
                            ]);
                            $extDeleted    = (int) ($extResult['num_deleted'] ?? 0);
                            $totalDeleted += $extDeleted;
                            \WP_CLI::log(sprintf(
                                '  Deleted %d document(s) for external strategy "%s".',
                                $extDeleted,
                                $extId
                            ));
                        } catch (\Exception $e) {
                            \WP_CLI::warning(sprintf(
                                'Failed to clear external strategy "%s": %s',
                                $extId,
                                $e->getMessage()
                            ));
                        }
                    }
                }
            }
        }

        // ── Summary ──────────────────────────────────────────────────────────
        \WP_CLI::log('');
        if ($isDryRun) {
            \WP_CLI::success('Dry run complete. No documents were deleted.');
        } else {
            \WP_CLI::success(sprintf(
                'Done. %d document(s) deleted from "%s".',
                $totalDeleted,
                $collectionName
            ));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // sync-external command
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Sync one or all external indexing strategies (e.g. e-services from an API).
     *
     * External strategies are registered by third-party plugins via the
     * 'Municipio/TypesenseSearch/RegisterStrategies' action and extend
     * AbstractExternalIndexingStrategy. They pull content from outside
     * WordPress and have no post lifecycle hooks, so syncing must be triggered
     * explicitly (this command) or via WP-Cron.
     *
     * ## OPTIONS
     *
     * [<identifier>]
     * : Identifier of a single strategy to sync (e.g. "pitea-eservice").
     *   Omit to sync all registered external strategies.
     *
     * [--dry-run]
     * : List registered external strategies and the source URL each would
     *   hit, without actually fetching or upserting anything.
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * ## EXAMPLES
     *
     *   # Sync all registered external strategies.
     *   wp typesense sync-external
     *
     *   # Sync only the Piteå e-services strategy.
     *   wp typesense sync-external pitea-eservice
     *
     *   # Preview without fetching or writing anything.
     *   wp typesense sync-external --dry-run
     *
     * @subcommand sync-external
     * @when after_wp_load
     *
     * @param array<int,string>    $args       Positional arguments (0 = optional identifier).
     * @param array<string,string> $assocArgs  Named arguments.
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

    // ─────────────────────────────────────────────────────────────────────────
    // list-external command
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * List all registered external indexing strategies.
     *
     * Displays the identifier of each strategy registered via the
     * 'Municipio/TypesenseSearch/RegisterStrategies' action. Use the
     * identifier with `sync-external` or `clear --only-external=<identifier>`.
     *
     * ## EXAMPLES
     *
     *   wp typesense list-external
     *
     * @subcommand list-external
     * @when after_wp_load
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

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

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
     * Resolve which post types to index.
     *
     * Uses --post-type if supplied, otherwise falls back to the post types
     * enabled in the plugin settings. Warns and removes any post types that
     * are not enabled in settings (unless explicitly passed via --post-type).
     *
     * @param array<string,string> $assocArgs
     * @return string[]
     */
    private function resolvePostTypes(array $assocArgs): array
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
     * Free object cache and reset post data between batches to keep memory
     * usage stable during large indexing runs.
     */
    private function freeMemory(): void
    {
        wp_cache_flush_runtime();
    }

    /**
     * Resolve which post types to target for the clear command.
     *
     * When $rawArg is null, falls back to all post types enabled in settings.
     * Unlike resolvePostTypes(), this method does NOT require types to be
     * enabled in settings when they are explicitly requested — it only checks
     * that the post type exists, so stale/disabled types can still be cleared.
     *
     * @param string|null $rawArg Comma-separated post types, or null.
     * @return string[]
     */
    private function resolvePostTypesForClear(?string $rawArg): array
    {
        if ($rawArg === null) {
            // Default: all types that are enabled in settings.
            $all = array_keys(Settings::getIndexablePostTypes());
            return array_values(array_filter(
                $all,
                fn(string $type) => Settings::isPostTypeEnabled($type)
            ));
        }

        $requested = array_map('trim', explode(',', $rawArg));
        $resolved  = [];

        foreach ($requested as $type) {
            if (!post_type_exists($type)) {
                \WP_CLI::warning(sprintf('Post type "%s" does not exist — skipping.', $type));
                continue;
            }
            $resolved[] = $type;
        }

        return $resolved;
    }
}
