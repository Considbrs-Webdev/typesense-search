<?php

namespace TypesenseSearch\SearchStatistics;

use TypesenseSearch\Services\SettingsRepository;

/**
 * Schedules daily data-retention cleanup.
 */
class Retention
{
    public const HOOK = 'typesense_search_prune_statistics';

    public function __construct(private SettingsRepository $settings, private Repository $repository)
    {
        add_action(self::HOOK, [$this, 'prune']);
        add_action('init', [$this, 'schedule']);
    }

    public static function activate(): void
    {
        Database::migrate();

        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::HOOK);
        }
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(self::HOOK);
    }

    public function schedule(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::HOOK);
        }
    }

    public function prune(): void
    {
        $this->repository->prune($this->settings->getSearchStatisticsRetentionDays());
    }
}
