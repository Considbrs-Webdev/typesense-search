<?php
if ($activeTab !== 'logging') {
    return;
}

$indexingLog = \TypesenseSearch\Logger\IndexingLog::getLog();
$lastRun     = $indexingLog['last_run'];
$entries     = $indexingLog['entries'];

$formatLogTime = static function ($timestamp): string {
    $timestamp = (int) $timestamp;
    if ($timestamp <= 0) {
        return '—';
    }

    return date_i18n(
        sprintf('%s %s', get_option('date_format'), get_option('time_format')),
        $timestamp
    );
};

$statusLabel = static function (?array $run): string {
    if (!$run) {
        return __('No runs logged', 'typesense-search');
    }

    if (($run['status'] ?? '') === 'running') {
        return __('Running', 'typesense-search');
    }

    return ((int) ($run['failed'] ?? 0)) > 0
        ? __('Completed with issues', 'typesense-search')
        : __('Completed successfully', 'typesense-search');
};
?>

<div class="ts-settings__panel" id="ts-tab-logging">

    <div class="ts-settings__card ts-log-card">
        <div class="ts-settings__card-header">
            <div class="ts-stats-card__header-text">
                <h2><?php esc_html_e('Indexing log', 'typesense-search'); ?></h2>
                <p><?php esc_html_e('Review the latest indexing run and any document-level issues that were captured while indexing.', 'typesense-search'); ?></p>
            </div>
            <?php if ($lastRun || !empty($entries)) : ?>
                <button type="button" id="ts-log-clear" class="button button-secondary ts-log-clear">
                    <svg class="ts-log-clear__spinner" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                    <svg class="ts-log-clear__icon" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    <?php esc_html_e('Clear log', 'typesense-search'); ?>
                </button>
            <?php endif; ?>
        </div>

        <?php if (!$lastRun && empty($entries)) : ?>
            <p class="ts-settings__empty"><?php esc_html_e('No indexing activity has been logged yet.', 'typesense-search'); ?></p>
        <?php else : ?>
            <div class="ts-log-summary">
                <div class="ts-log-summary__item">
                    <span class="ts-log-summary__value ts-log-summary__value--<?php echo esc_attr($lastRun['status'] ?? 'empty'); ?>">
                        <?php echo esc_html($statusLabel($lastRun)); ?>
                    </span>
                    <span class="ts-log-summary__label"><?php esc_html_e('Last run status', 'typesense-search'); ?></span>
                </div>
                <div class="ts-log-summary__item">
                    <span class="ts-log-summary__value"><?php echo esc_html((string) ($lastRun['indexed'] ?? 0)); ?></span>
                    <span class="ts-log-summary__label"><?php esc_html_e('Indexed', 'typesense-search'); ?></span>
                </div>
                <div class="ts-log-summary__item">
                    <span class="ts-log-summary__value"><?php echo esc_html((string) ($lastRun['skipped'] ?? 0)); ?></span>
                    <span class="ts-log-summary__label"><?php esc_html_e('Skipped', 'typesense-search'); ?></span>
                </div>
                <div class="ts-log-summary__item">
                    <span class="ts-log-summary__value"><?php echo esc_html((string) ($lastRun['failed'] ?? count($entries))); ?></span>
                    <span class="ts-log-summary__label"><?php esc_html_e('Failed', 'typesense-search'); ?></span>
                </div>
            </div>

            <div class="ts-log-meta">
                <span><?php esc_html_e('Started:', 'typesense-search'); ?> <strong><?php echo esc_html($formatLogTime($lastRun['started_at'] ?? 0)); ?></strong></span>
                <span><?php esc_html_e('Finished:', 'typesense-search'); ?> <strong><?php echo esc_html($formatLogTime($lastRun['ended_at'] ?? 0)); ?></strong></span>
                <?php if (!empty($lastRun['label'])) : ?>
                    <span><?php esc_html_e('Scope:', 'typesense-search'); ?> <strong><?php echo esc_html((string) $lastRun['label']); ?></strong></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($lastRun['message'])) : ?>
                <p class="ts-log-message"><?php echo esc_html((string) $lastRun['message']); ?></p>
            <?php endif; ?>

            <?php if (empty($entries)) : ?>
                <p class="ts-log-empty"><?php esc_html_e('No document-level issues were logged for this run.', 'typesense-search'); ?></p>
            <?php else : ?>
                <div class="ts-log-table-wrap">
                    <table class="widefat fixed striped ts-log-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Time', 'typesense-search'); ?></th>
                                <th><?php esc_html_e('Level', 'typesense-search'); ?></th>
                                <th><?php esc_html_e('Document', 'typesense-search'); ?></th>
                                <th><?php esc_html_e('Strategy', 'typesense-search'); ?></th>
                                <th><?php esc_html_e('Issue', 'typesense-search'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry) : ?>
                                <tr>
                                    <td><?php echo esc_html($formatLogTime($entry['timestamp'] ?? 0)); ?></td>
                                    <td>
                                        <span class="ts-log-level ts-log-level--<?php echo esc_attr((string) ($entry['level'] ?? 'error')); ?>">
                                            <?php echo esc_html(ucfirst((string) ($entry['level'] ?? 'error'))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($entry['document_id'])) : ?>
                                            <code><?php echo esc_html((string) $entry['document_id']); ?></code>
                                        <?php endif; ?>
                                        <?php if (!empty($entry['document_label'])) : ?>
                                            <span><?php echo esc_html((string) $entry['document_label']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo !empty($entry['strategy']) ? esc_html((string) $entry['strategy']) : '—'; ?></td>
                                    <td><?php echo esc_html((string) ($entry['message'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div>
