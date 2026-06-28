<?php

namespace TypesenseSearch\CLI\Actions;

use TypesenseSearch\Admin\Settings;
use TypesenseSearch\App;
use TypesenseSearch\Typesense\ClientFactory;

/**
 * Handles the `wp typesense clear` subcommand.
 *
 * Removes indexed documents from the Typesense collection by post type,
 * by PDF attachment type, or by external strategy — with support for
 * dry-run and selective targeting.
 *
 * @package TypesenseSearch\CLI\Actions
 */
class ClearAction
{
    /**
     * Run the clear operation.
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assocArgs
     */
    public function handle(array $args, array $assocArgs): void
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
