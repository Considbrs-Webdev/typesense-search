<?php

namespace TypesenseSearch\Admin;

use TypesenseSearch\Admin\Settings\OptionKeys;
use TypesenseSearch\Admin\Settings\Sanitizers;
use TypesenseSearch\Admin\Settings\SettingsPage;
use TypesenseSearch\Admin\Settings\SettingsRegistry;
use TypesenseSearch\Helper\PdfToText;
use TypesenseSearch\Services\SettingsRepository;

/**
 * Class Settings
 *
 * Registers the Typesense Search settings page under WordPress Settings.
 *
 * Extends OptionKeys so all OPTION_* and OPTION_GROUP_* constants remain
 * accessible as Settings::OPTION_* for backward compatibility.
 * Uses the Sanitizers trait so the sanitize_* methods remain on this class
 * for test and external compatibility.
 *
 * @package TypesenseSearch\Admin
 */
class Settings extends OptionKeys
{
    use Sanitizers;

    public function __construct()
    {
        $settingsPage     = new SettingsPage();
        $settingsRegistry = new SettingsRegistry();

        add_action('admin_menu', [$settingsPage, 'addSettingsPage']);
        add_action('admin_init', [$settingsRegistry, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$settingsPage, 'enqueueAssets']);
        add_filter('script_loader_tag', [$settingsPage, 'addModuleType'], 10, 2);
    }

    // ── Static helpers ──────────────────────────────────────────────────────
    // Kept here for backward compatibility with all existing callers.
    // Canonical implementations live in SettingsRepository.

    /**
     * Check whether the pdftotext binary is available on the server.
     *
     * @deprecated Use SettingsRepository::isPdfToTextAvailable() instead.
     */
    public static function isPdfToTextAvailable(): bool
    {
        return SettingsRepository::isPdfToTextAvailable();
    }

    /**
     * Return all public, indexable post types (excluding attachments).
     *
     * @return \WP_Post_Type[]
     * @deprecated Use SettingsRepository::getIndexablePostTypes() instead.
     */
    public static function getIndexablePostTypes(): array
    {
        return SettingsRepository::getIndexablePostTypes();
    }

    /**
     * Check whether a specific post type is enabled for indexing.
     *
     * @deprecated Use SettingsRepository::isPostTypeEnabled() via an injected instance instead.
     */
    public static function isPostTypeEnabled(string $postType): bool
    {
        return (new SettingsRepository())->isPostTypeEnabled($postType);
    }

    /**
     * Check whether the Modularity plugin is available.
     *
     * @deprecated Use SettingsRepository::isModularityAvailable() instead.
     */
    public static function isModularityAvailable(): bool
    {
        return SettingsRepository::isModularityAvailable();
    }
}
