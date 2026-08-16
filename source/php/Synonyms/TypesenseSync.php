<?php

namespace TypesenseSearch\Synonyms;

use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Typesense\AdminApi;
use TypesenseSearch\Typesense\Collection;

/**
 * Syncs WordPress-managed synonym rules to a Typesense synonym set.
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

        $setName = $this->synonymSetName($collectionName);
        $items   = array_values(array_map(
            fn (array $rule): array => $this->ruleToSynonymItem($rule),
            array_filter($rules, static fn (array $rule): bool => count((array) ($rule['terms'] ?? [])) >= 2)
        ));

        $synonymResponse = $this->adminApi->request('PUT', "{$remote}/synonym_sets/" . rawurlencode($setName), [
            'items' => $items,
        ]);

        if (!$synonymResponse['ok']) {
            return $synonymResponse;
        }

        $existingSetsResponse = $this->getCollectionSynonymSets($remote, $collectionName);
        if (!$existingSetsResponse['ok']) {
            return $existingSetsResponse;
        }

        $synonymSets = array_values(array_unique(array_merge($existingSetsResponse['sets'], [$setName])));

        $collectionResponse = $this->adminApi->request('PATCH', "{$remote}/collections/" . rawurlencode($collectionName), [
            'synonym_sets' => $synonymSets,
        ]);

        if (!$collectionResponse['ok']) {
            return $collectionResponse;
        }

        return [
            'ok'      => true,
            'message' => sprintf(
                /* translators: %d: number of synonym rules */
                _n('Synced %d synonym rule.', 'Synced %d synonym rules.', count($items), 'typesense-search'),
                count($items)
            ),
        ];
    }

    private function synonymSetName(string $collectionName): string
    {
        return 'wordpress-synonyms-' . $collectionName;
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleToSynonymItem(array $rule): array
    {
        $item = [
            'id'       => 'wp-synonym-' . (int) $rule['id'],
            'synonyms' => array_values(array_map('strval', (array) ($rule['terms'] ?? []))),
        ];

        // Typesense resolves locale-specific synonyms using the locale of the
        // highest-weighted query_by field. Keep synonym items aligned with the
        // locale added to searchable fields when stemming is enabled.
        if ($this->settings->isStemmingEnabled()) {
            $item['locale'] = Collection::getStemmingLocale();
        }

        return $item;
    }

    /**
     * @return array{ok: bool, message: string, sets: array<int, string>}
     */
    private function getCollectionSynonymSets(string $remote, string $collectionName): array
    {
        $response = $this->adminApi->request('GET', "{$remote}/collections/" . rawurlencode($collectionName));
        if (!$response['ok']) {
            return ['ok' => false, 'message' => $response['message'], 'sets' => []];
        }

        $body = json_decode($response['body'], true);
        $sets = is_array($body['synonym_sets'] ?? null) ? array_filter(array_map('strval', $body['synonym_sets'])) : [];

        return ['ok' => true, 'message' => '', 'sets' => array_values($sets)];
    }
}
