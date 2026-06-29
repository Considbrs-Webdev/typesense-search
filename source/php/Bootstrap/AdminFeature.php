<?php

namespace TypesenseSearch\Bootstrap;

use TypesenseSearch\Admin;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Typesense\AdminApi;
use TypesenseSearch\Typesense\ServerCapabilities;

/**
 * Wires all admin-panel components.
 *
 * @package TypesenseSearch\Bootstrap
 */
class AdminFeature
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function register(): void
    {
        $capabilities = new ServerCapabilities(new AdminApi($this->settings));

        new Admin\Settings();
        new Admin\SettingsAjax($this->settings);
        new Admin\MetaBox($this->settings);
        new Admin\PinnedResultsPage($this->settings, $capabilities);
    }
}
