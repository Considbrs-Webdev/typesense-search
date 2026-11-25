<?php

namespace TypesenseSearch\Admin;

use TypesenseSearch\Admin\Settings\OptionKeys;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Typesense\ClientFactory;
use TypesenseSearch\Typesense\SchemaInspector;

/**
 * Warns administrators when schema-affecting settings (embedded search,
 * embedding model, stemming) differ from the verified live Typesense schema.
 *
 * Unlike URL changes, schema compatibility is derived directly from
 * SchemaInspector's verified state, so the notice clears automatically as soon
 * as `wp typesense rebuild`, collection creation, or a status check confirms
 * the live schema matches.
 */
class SchemaRebuildNotice
{
    private const WATCHED_OPTIONS = [
        OptionKeys::OPTION_VECTOR_ENABLED,
        OptionKeys::OPTION_VECTOR_MODEL,
        OptionKeys::OPTION_STEMMING_ENABLED,
    ];

    public function __construct(private SettingsRepository $settings = new SettingsRepository())
    {
    }

    public function register(): void
    {
        foreach (self::WATCHED_OPTIONS as $option) {
            add_action('update_option_' . $option, [$this, 'refreshLiveSchema']);
        }

        add_action('admin_notices', [$this, 'renderNotice']);
    }

    /**
     * Best-effort live schema inspection when an admin saves a schema-affecting
     * option so the notice reflects actual collection state immediately.
     */
    public function refreshLiveSchema(): void
    {
        if (!$this->settings->canUseTypesense()) {
            return;
        }

        $remote         = $this->settings->getRemote();
        $collectionName = $this->settings->getCollectionName();
        if ($remote === '' || $collectionName === '') {
            return;
        }

        try {
            $client = ClientFactory::fromSettings($this->settings, 5);
            if ($client !== null) {
                SchemaInspector::refreshLiveState($client, $remote, $collectionName);
            }
        } catch (\Throwable $e) {
            // Non-fatal: the notice will still guide the administrator if unverified.
        }
    }

    public function renderNotice(): void
    {
        if (!current_user_can('manage_options') || !$this->settings->canUseTypesense()) {
            return;
        }

        $remote         = $this->settings->getRemote();
        $collectionName = $this->settings->getCollectionName();
        if ($remote === '' || $collectionName === '') {
            return;
        }

        $verified = SchemaInspector::getVerifiedState($remote, $collectionName);
        $compat   = SchemaInspector::evaluateCompatibility(
            $this->settings->isVectorSearchEnabled(),
            $this->settings->getVectorModel(),
            $this->settings->isStemmingEnabled(),
            $verified
        );

        $messages = $compat['ok'] ? [] : $compat['reasons'];

        $isMultiSearchNotice = false;
        $networkMode         = $this->settings->network()->isNetworkActivated();
        if ($networkMode && $compat['ok']) {
            $multiSearch = $this->settings->getMultiSearchResolution();
            if (!empty($multiSearch['vector_paused_reason'])) {
                $messages[]          = (string) $multiSearch['vector_paused_reason'];
                $isMultiSearchNotice = true;
            }
        }

        if ($messages === []) {
            return;
        }

        if ($isMultiSearchNotice && current_user_can('manage_network_options')) {
            $actionUrl   = network_admin_url('settings.php?page=typesense-network&tab=multi-search');
            $actionLabel = __('Configure multi-search', 'typesense-search');
        } elseif ($networkMode) {
            $actionUrl   = admin_url('admin.php?page=' . OptionKeys::PAGE_SLUG . '&tab=advanced-settings');
            $actionLabel = __('Open search settings', 'typesense-search');
        } else {
            $actionUrl   = admin_url('admin.php?page=' . OptionKeys::PAGE_SLUG . '&tab=status');
            $actionLabel = __('Check status', 'typesense-search');
        }

        printf(
            '<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
            esc_html__('Typesense Search:', 'typesense-search'),
            esc_html(implode(' ', $messages)),
            esc_url($actionUrl),
            esc_html($actionLabel)
        );
    }
}
