<?php

namespace TypesenseSearch\Bootstrap;

use TypesenseSearch\PinnedResults\Database as PinnedResultsDatabase;
use TypesenseSearch\PinnedResults\Repository as PinnedResultsRepository;
use TypesenseSearch\PinnedResults\RestController as PinnedResultsRestController;
use TypesenseSearch\PinnedResults\TypesenseSync as PinnedResultsTypesenseSync;
use TypesenseSearch\Services\SettingsRepository;

/**
 * Wires the pinned-results subsystem.
 *
 * @package TypesenseSearch\Bootstrap
 */
class PinnedResultsFeature
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function register(): void
    {
        add_action('plugins_loaded', [PinnedResultsDatabase::class, 'maybeMigrate']);

        $pinnedResults = new PinnedResultsRepository();

        new PinnedResultsRestController(
            $this->settings,
            $pinnedResults,
            new PinnedResultsTypesenseSync($this->settings)
        );
    }
}
