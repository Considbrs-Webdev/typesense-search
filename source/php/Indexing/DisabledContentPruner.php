<?php

namespace TypesenseSearch\Indexing;

use TypesenseSearch\Admin\Settings\OptionKeys;
use TypesenseSearch\Logger\LoggerInterface;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Services\TypesenseClientService;

/**
 * Removes documents from Typesense when indexing settings are turned off.
 *
 * Post saves and attachment edits only run after content changes. When an
 * administrator disables a post type or PDF indexing in settings, no content
 * lifecycle event fires, so previously indexed documents must be pruned here.
 */
class DisabledContentPruner
{
    public function __construct(
        private readonly TypesenseClientService $clientService,
        private readonly SettingsRepository $settings,
        private readonly LoggerInterface $logger
    ) {
    }

    public function register(): void
    {
        add_action(
            'update_option_' . OptionKeys::OPTION_POST_TYPES,
            [$this, 'onPostTypesUpdated'],
            10,
            2
        );

        add_action(
            'update_option_' . OptionKeys::OPTION_INDEX_PDF,
            [$this, 'onPdfIndexingUpdated'],
            10,
            2
        );
    }

    /**
     * Delete documents for post types that were enabled before but are now off.
     */
    public function onPostTypesUpdated(mixed $oldValue, mixed $newValue): void
    {
        $removedTypes = array_values(array_diff(
            $this->normalizePostTypes($oldValue),
            $this->normalizePostTypes($newValue)
        ));

        foreach ($removedTypes as $postType) {
            $this->deleteByFilter('type:=' . $postType);
        }
    }

    /**
     * Delete indexed PDF attachment documents when PDF indexing is disabled.
     */
    public function onPdfIndexingUpdated(mixed $oldValue, mixed $newValue): void
    {
        if ((bool) $oldValue && !(bool) $newValue) {
            $this->deleteByFilter('type:=attachment');
        }
    }

    /**
     * @return string[]
     */
    private function normalizePostTypes(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn(mixed $postType): string => sanitize_key($postType),
            $value
        ))));
    }

    private function deleteByFilter(string $filterBy): void
    {
        $client         = $this->clientService->getClient();
        $collectionName = $this->settings->getCollectionName();

        if ($client === null || $collectionName === '') {
            return;
        }

        try {
            $client->collections[$collectionName]->documents->delete([
                'filter_by' => $filterBy,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                '[TypesenseSearch] Failed to prune disabled content from index (%s): %s',
                $filterBy,
                $e->getMessage()
            ));
        }
    }
}
