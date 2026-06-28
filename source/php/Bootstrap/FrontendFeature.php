<?php

namespace TypesenseSearch\Bootstrap;

use TypesenseSearch\Frontend;
use TypesenseSearch\Services\SettingsRepository;

/**
 * Wires all frontend-facing components.
 *
 * @package TypesenseSearch\Bootstrap
 */
class FrontendFeature
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function register(): void
    {
        new Frontend\Assets();
        new Frontend\EnrichSearchTemplate();
        new Frontend\TypesenseConfig($this->settings);
    }
}
