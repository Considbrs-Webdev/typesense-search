<?php

namespace TypesenseSearch\CLI\Actions;

use TypesenseSearch\Admin\Settings;
use TypesenseSearch\Typesense\ClientFactory;

/**
 * Handles the `wp typesense rebuild` subcommand.
 *
 * Drops the Typesense collection, recreates it from the plugin schema, and
 * optionally re-indexes all published posts in one operation.
 *
 * @package TypesenseSearch\CLI\Actions
 */
class RebuildAction
{
    public function __construct(private IndexAction $indexAction)
    {
    }

    /**
     * Run the rebuild operation.
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assocArgs
     */
    public function handle(array $args, array $assocArgs): void
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
                $enabledTypes = $this->indexAction->resolvePostTypes($assocArgs);
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
        $this->indexAction->handle($args, $forwardArgs);
    }
}
