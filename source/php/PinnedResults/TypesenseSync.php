<?php

namespace TypesenseSearch\PinnedResults;

use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Typesense\AdminApi;

/**
 * Syncs WordPress-managed pinned result rules to Typesense curation sets.
 */
class TypesenseSync
{
    public function __construct(
        private SettingsRepository $settings,
        private AdminApi $adminApi,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     * @return array{ok: bool, message: string}
     */
    public function sync(array $rules): array
    {
        $remote         = rtrim($this->settings->getRemote(), '/');
        $collectionName = $this->settings->getCollectionName();

        if ($remote === '' || $this->settings->getAdminKey() === '' || $collectionName === '') {
            return ['ok' => false, 'message' => __('Typesense connection settings are incomplete.', 'typesense-search')];
        }

        $setName      = $this->curationSetName($collectionName);
        $enabledRules = array_values(array_filter($rules, static fn (array $rule): bool => ($rule['enabled'] ?? true) !== false));
        $items        = array_values(array_map(fn (array $rule): array => $this->ruleToCurationItem($rule), $enabledRules));

        $curationResponse = $this->adminApi->request('PUT', "{$remote}/curation_sets/" . rawurlencode($setName), [
            'items' => $items,
        ]);

        if (!$curationResponse['ok']) {
            return $curationResponse;
        }

        $existingSetsResponse = $this->getCollectionCurationSets($remote, $collectionName);
        if (!$existingSetsResponse['ok']) {
            return $existingSetsResponse;
        }

        $curationSets = array_values(array_unique(array_merge($existingSetsResponse['sets'], [$setName])));

        $collectionResponse = $this->adminApi->request('PATCH', "{$remote}/collections/" . rawurlencode($collectionName), [
            'curation_sets' => $curationSets,
        ]);

        if (!$collectionResponse['ok']) {
            return $collectionResponse;
        }

        return [
            'ok'      => true,
            'message' => sprintf(
                /* translators: %d: number of pinned-result rules */
                _n('Synced %d pinned result rule.', 'Synced %d pinned result rules.', count($items), 'typesense-search'),
                count($items)
            ),
        ];
    }

    private function curationSetName(string $collectionName): string
    {
        return 'wordpress-pinned-results-' . $collectionName;
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleToCurationItem(array $rule): array
    {
        $postIds = array_values(array_map('strval', (array) ($rule['post_ids'] ?? [])));
        $includes = [];
        foreach ($postIds as $index => $postId) {
            $includes[] = [
                'id'       => $postId,
                'position' => $index + 1,
            ];
        }

        return [
            'id'       => 'wp-pinned-result-' . (int) $rule['id'],
            'rule'     => [
                'query' => (string) $rule['phrase'],
                'match' => ($rule['match_type'] ?? 'exact') === 'contains' ? 'contains' : 'exact',
            ],
            'includes' => $includes,
            'metadata' => [
                'source'      => 'typesense-search-wordpress',
                'wordpressId' => (int) $rule['id'],
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, sets: array<int, string>}
     */
    private function getCollectionCurationSets(string $remote, string $collectionName): array
    {
        $response = $this->adminApi->request('GET', "{$remote}/collections/" . rawurlencode($collectionName));
        if (!$response['ok']) {
            return ['ok' => false, 'message' => $response['message'], 'sets' => []];
        }

        $body = json_decode($response['body'], true);
        $sets = is_array($body['curation_sets'] ?? null) ? array_filter(array_map('strval', $body['curation_sets'])) : [];

        return ['ok' => true, 'message' => '', 'sets' => array_values($sets)];
    }
}
