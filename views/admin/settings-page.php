<?php

/**
 * Settings page view for Typesense Search.
 *
 * Available variables:
 *  @var string        $activeTab        Current active tab slug.
 *  @var array<string,string> $tabs      Map of tab slug => label.
 *  @var \WP_Post_Type[] $postTypes      All indexable post types.
 *  @var string[]      $enabledPostTypes Slugs of currently enabled post types.
 *  @var array[]       $facets           Saved facet entries (field, label, placeholder).
 */

use TypesenseSearch\Admin\Settings;

$pageUrl = admin_url('options-general.php?page=' . Settings::PAGE_SLUG);
?>

<div class="wrap ts-settings">

    <h1 class="ts-settings__title">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="ts-settings__icon"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <?php esc_html_e('Typesense Search', 'typesense-search'); ?>
    </h1>

    <p class="ts-settings__subtitle">
        <?php esc_html_e('Configure your Typesense server connection and choose which content to index.', 'typesense-search'); ?>
    </p>

    <?php settings_errors('typesense_search_notices'); ?>

    <!-- Tab navigation -->
    <nav class="nav-tab-wrapper ts-settings__tabs" aria-label="<?php esc_attr_e('Settings sections', 'typesense-search'); ?>">
        <?php foreach ($tabs as $slug => $label) : ?>
            <a href="<?php echo esc_url($pageUrl . '&tab=' . $slug); ?>"
               class="nav-tab <?php echo $activeTab === $slug ? 'nav-tab-active' : ''; ?>">
                <?php if ($slug === 'connection') : ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1"/></svg>
                <?php elseif ($slug === 'statistics') : ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                <?php elseif ($slug === 'status') : ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <?php else : ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                <?php endif; ?>
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php include __DIR__ . '/settings-tabs/connection.php'; ?>
    <?php include __DIR__ . '/settings-tabs/content.php'; ?>
    <?php include __DIR__ . '/settings-tabs/facetting.php'; ?>
    <?php include __DIR__ . '/settings-tabs/statistics.php'; ?>
    <?php include __DIR__ . '/settings-tabs/status.php'; ?>

</div><!-- .wrap -->
