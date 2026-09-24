<?php

namespace TypesenseSearch\Bootstrap;

use TypesenseSearch\ExternalPages\Database as ExternalPagesDatabase;
use TypesenseSearch\ExternalPages\IndexingStrategy as ExternalPagesIndexingStrategy;
use TypesenseSearch\ExternalPages\Repository as ExternalPagesRepository;
use TypesenseSearch\ExternalPages\RestController as ExternalPagesRestController;
use TypesenseSearch\Logger\LoggerInterface;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Services\TypesenseClientService;

/**
 * Wires the external pages subsystem.
 *
 * The indexing strategy itself is registered by IndexingFeature so it takes
 * part in `wp typesense index` runs.
 *
 * @package TypesenseSearch\Bootstrap
 */
class ExternalPagesFeature
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly TypesenseClientService $clientService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function register(): void
    {
        add_action('plugins_loaded', [ExternalPagesDatabase::class, 'maybeMigrate']);

        $repository = new ExternalPagesRepository();

        new ExternalPagesRestController(
            $this->settings,
            $repository,
            new ExternalPagesIndexingStrategy($this->clientService, $this->settings, $this->logger, $repository)
        );
    }
}
