<?php

namespace TypesenseSearch\CLI;

use TypesenseSearch\Admin\Settings;
use TypesenseSearch\Indexing\Indexer;
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
     *   (this is already enforced by Indexer::shouldIndex — use this flag to
     *   make it explicit in the output).
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * [--sleep=<milliseconds>]
     * : Sleep for the given number of milliseconds after each post. Useful
     *   for visually verifying the progress bar during development/testing.
     *   Example: --sleep=200
     *
     * ## EXAMPLES
     *
     *   wp typesense index
     *   wp typesense index --dry-run
     *   wp typesense index --post-type=post,page
     *   wp typesense index --batch-size=50 --yes
     *   wp typesense index --dry-run --sleep=200
     *
     * @subcommand index
     * @when after_wp_load
     *
     * @param array<int,string>    $args       Positional arguments (unused).
     * @param array<string,string> $assocArgs  Named arguments.
     */
    public function index(array $args, array $assocArgs): void
    {
        $isDryRun   = \WP_CLI\Utils\get_flag_value($assocArgs, 'dry-run', false);
        $rawBatch   = \WP_CLI\Utils\get_flag_value($assocArgs, 'batch-size', null);
        $batchSize  = $rawBatch === null ? -1 : max(1, (int) $rawBatch);
        $skipYes    = \WP_CLI\Utils\get_flag_value($assocArgs, 'yes', false);
        $sleepUs    = (int) \WP_CLI\Utils\get_flag_value($assocArgs, 'sleep', 0) * 1000;

        // ── Resolve post types ──────────────────────────────────────────────
        $enabledTypes   = $this->resolvePostTypes($assocArgs);
        $enabledObjects = Settings::getIndexablePostTypes();

        if (empty($enabledTypes)) {
            \WP_CLI::error(
                'No post types are enabled for indexing. ' .
                'Enable at least one on the Typesense Search settings page, ' .
                'or pass --post-type=<type>.'
            );
        }

        // ── Dry-run banner ──────────────────────────────────────────────────
        if ($isDryRun) {
            \WP_CLI::warning('DRY RUN — no documents will be written to Typesense.');
        }

        // ── Confirmation ────────────────────────────────────────────────────
        if (!$isDryRun && !$skipYes) {
            \WP_CLI::confirm(
                sprintf(
                    'This will index posts of type(s): %s. Continue?',
                    implode(', ', $enabledTypes)
                )
            );
        }

        // ── Count totals per post type for the progress bar ─────────────────
        $totalsByType = [];
        foreach ($enabledTypes as $postType) {
            $totalsByType[$postType] = (int) wp_count_posts($postType)->publish;
        }

        $grandTotal = array_sum($totalsByType);

        if ($grandTotal === 0) {
            \WP_CLI::success('No published posts found for the selected post types.');
            return;
        }

        \WP_CLI::log(
            sprintf(
                'Found %d published post(s) across %d post type(s).',
                $grandTotal,
                count($enabledTypes)
            )
        );

        // ── Counters ────────────────────────────────────────────────────────
        $totalIndexed = 0;
        $totalSkipped = 0;
        $totalFailed  = 0;

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
                    if (!Indexer::shouldIndex($post)) {
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

                    $result = Indexer::index($post);

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

        // ── Summary ─────────────────────────────────────────────────────────
        \WP_CLI::log('');
        $dryLabel = $isDryRun ? ' (dry run)' : '';

        if ($totalFailed > 0) {
            \WP_CLI::warning(sprintf(
                'Finished%s: %d indexed, %d skipped, %d failed.',
                $dryLabel,
                $totalIndexed,
                $totalSkipped,
                $totalFailed
            ));
        } else {
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
     * @subcommand clear
     * @when after_wp_load
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assocArgs
     */
    public function clear(array $args, array $assocArgs): void
    {
        $isDryRun = \WP_CLI\Utils\get_flag_value($assocArgs, 'dry-run', false);
        $skipYes  = \WP_CLI\Utils\get_flag_value($assocArgs, 'yes', false);
        $sleepUs  = (int) \WP_CLI\Utils\get_flag_value($assocArgs, 'sleep', 0) * 1000;
        $rawType  = \WP_CLI\Utils\get_flag_value($assocArgs, 'post-type', null);
        $clearAll = $rawType === 'all';

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
        if ($clearAll) {
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
        $scopeLabel = $clearAll
            ? 'ALL documents'
            : sprintf('documents for post type(s): %s', implode(', ', $postTypes));

        if (!$isDryRun && !$skipYes) {
            \WP_CLI::confirm(sprintf(
                'This will permanently delete %s from collection "%s". Continue?',
                $scopeLabel,
                $collectionName
            ));
        }

        // ── Execute per operation ─────────────────────────────────────────────
        // $operations is a list of post-type strings, or [null] for "clear all".
        $operations     = $clearAll ? [null] : $postTypes;
        $enabledObjects = Settings::getIndexablePostTypes();
        $totalDeleted   = 0;

        foreach ($operations as $postType) {
            if ($postType !== null) {
                $label    = isset($enabledObjects[$postType])
                    ? sprintf('%s (%s)', $enabledObjects[$postType]->label, $postType)
                    : $postType;
                $filterBy = sprintf('post_type:=%s', $postType);
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
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

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
