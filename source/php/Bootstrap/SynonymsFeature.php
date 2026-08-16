<?php

namespace TypesenseSearch\Bootstrap;

use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Synonyms\Database as SynonymsDatabase;
use TypesenseSearch\Synonyms\Repository as SynonymsRepository;
use TypesenseSearch\Synonyms\RestController as SynonymsRestController;
use TypesenseSearch\Synonyms\TypesenseSync as SynonymsTypesenseSync;
use TypesenseSearch\Typesense\AdminApi;
use TypesenseSearch\Typesense\ServerCapabilities;

/**
 * Wires the synonyms subsystem.
 *
 * @package TypesenseSearch\Bootstrap
 */
class SynonymsFeature
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function register(): void
    {
        add_action('plugins_loaded', [SynonymsDatabase::class, 'maybeMigrate']);

        $adminApi     = new AdminApi($this->settings);
        $capabilities = new ServerCapabilities($adminApi);
        $synonyms     = new SynonymsRepository();

        new SynonymsRestController(
            $this->settings,
            $synonyms,
            new SynonymsTypesenseSync($this->settings, $adminApi),
            $capabilities
        );
    }
}
