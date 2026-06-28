<?php

namespace TypesenseSearch\Bootstrap;

use TypesenseSearch\Admin;
use TypesenseSearch\Services\SettingsRepository;

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
        new Admin\Settings();
        new Admin\SettingsAjax();
        new Admin\MetaBox();
        new Admin\PinnedResultsPage($this->settings);
    }
}
