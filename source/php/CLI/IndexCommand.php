<?php

namespace TypesenseSearch\CLI;

use TypesenseSearch\CLI\Actions\ClearAction;
use TypesenseSearch\CLI\Actions\IndexAction;
use TypesenseSearch\CLI\Actions\RebuildAction;
use TypesenseSearch\CLI\Actions\StatisticsAction;
use TypesenseSearch\CLI\Actions\SyncExternalAction;
use TypesenseSearch\SearchStatistics\Repository as SearchStatisticsRepository;
use TypesenseSearch\Services\SettingsRepository;

/**
 * WP-CLI command for managing the Typesense search index.
 *
 * This class is the thin entry point registered with WP-CLI. It reads CLI
 * arguments and delegates all business logic to focused action classes under
 * TypesenseSearch\CLI\Actions.
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
    private IndexAction       $indexAction;
    private RebuildAction     $rebuildAction;
    private ClearAction       $clearAction;
    private SyncExternalAction $syncExternalAction;
    private StatisticsAction  $statisticsAction;

    public function __construct(
        SettingsRepository $settings,
        SearchStatisticsRepository $searchStatistics
    ) {
        $this->indexAction        = new IndexAction();
        $this->rebuildAction      = new RebuildAction($this->indexAction);
        $this->clearAction        = new ClearAction();
        $this->syncExternalAction = new SyncExternalAction();
        $this->statisticsAction   = new StatisticsAction($settings, $searchStatistics);
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
        $this->statisticsAction->pruneSearchStatistics($args, $assocArgs);
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
        $this->statisticsAction->populateSearchLog($args, $assocArgs);
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
        $this->indexAction->handle($args, $assocArgs);
    }

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
        $this->rebuildAction->handle($args, $assocArgs);
    }

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
        $this->clearAction->handle($args, $assocArgs);
    }

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
        $this->syncExternalAction->syncExternal($args, $assocArgs);
    }

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
        $this->syncExternalAction->listExternal($args, $assocArgs);
    }
}
