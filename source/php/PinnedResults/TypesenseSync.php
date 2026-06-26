<?php

namespace TypesenseSearch\PinnedResults;

use TypesenseSearch\Services\SettingsRepository;

/**
 * Syncs WordPress-managed pinned result rules to Typesense curation sets.
 */
class TypesenseSync
{
    public function __construct(private SettingsRepository $settings)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     * @return array{ok: bool, message: string}
     */
    public function sync(array $rules): array
    {
        $remote = rtrim($this->settings->getRemote(), '/');
        $adminKey = $this->settings->getAdminKey();
        $collectionName = $this->settings->getCollectionName();

        if ($remote === '' || $adminKey === '' || $collectionName === '') {
            return ['ok' => false, 'message' => __('Typesense connection settings are incomplete.', 'typesense-search')];
        }

        $setName = $this->curationSetName($collectionName);
        $enabledRules = array_values(array_filter($rules, static fn (array $rule): bool => ($rule['enabled'] ?? true) !== false));
        $items = array_values(array_map(fn (array $rule): array => $this->ruleToCurationItem($rule), $enabledRules));

        $curationResponse = $this->request('PUT', "{$remote}/curation_sets/" . rawurlencode($setName), $adminKey, [
            'items' => $items,
        ]);

        if (!$curationResponse['ok']) {
            return $curationResponse;
        }

        $existingSetsResponse = $this->getCollectionCurationSets($remote, $adminKey, $collectionName);
        if (!$existingSetsResponse['ok']) {
            return $existingSetsResponse;
        }

        $curationSets = array_values(array_unique(array_merge($existingSetsResponse['sets'], [$setName])));

        $collectionResponse = $this->request('PATCH', "{$remote}/collections/" . rawurlencode($collectionName), $adminKey, [
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
    private function getCollectionCurationSets(string $remote, string $adminKey, string $collectionName): array
    {
        $response = $this->request('GET', "{$remote}/collections/" . rawurlencode($collectionName), $adminKey);
        if (!$response['ok']) {
            return ['ok' => false, 'message' => $response['message'], 'sets' => []];
        }

        $body = json_decode($response['body'], true);
        $sets = is_array($body['curation_sets'] ?? null) ? array_filter(array_map('strval', $body['curation_sets'])) : [];

        return ['ok' => true, 'message' => '', 'sets' => array_values($sets)];
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array{ok: bool, message: string, body: string}
     */
    private function request(string $method, string $url, string $adminKey, ?array $body = null): array
    {
        $args = [
            'method'  => $method,
            'timeout' => 10,
            'headers' => [
                'Content-Type'          => 'application/json',
                'X-TYPESENSE-API-KEY'   => $adminKey,
            ],
        ];

        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return ['ok' => false, 'message' => $response->get_error_message(), 'body' => ''];
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $responseBody = (string) wp_remote_retrieve_body($response);
        if ($statusCode < 200 || $statusCode >= 300) {
            $message = trim($responseBody);

            return [
                'ok'      => false,
                'message' => $message !== ''
                    ? sprintf('Typesense returned HTTP %d: %s', $statusCode, $message)
                    : sprintf('Typesense returned HTTP %d.', $statusCode),
                'body'    => $responseBody,
            ];
        }

        return ['ok' => true, 'message' => '', 'body' => $responseBody];
    }
}
