<?php

namespace TypesenseSearch\Admin\Ajax;

use Typesense\Exceptions\RequestUnauthorized;
use TypesenseSearch\Admin\SettingsAjax;
use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Typesense\ClientFactory;
use TypesenseSearch\Typesense\Collection;

/**
 * AJAX handlers for testing the Typesense connection and checking saved-settings status.
 *
 * Covers:
 *   - typesense_test_connection   (handle)
 *   - typesense_check_status      (handleCheckStatus)
 *
 * @package TypesenseSearch\Admin\Ajax
 */
class ConnectionActions
{
    use AjaxHelpers;

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function register(): void
    {
        add_action('wp_ajax_' . SettingsAjax::AJAX_ACTION_TEST,         [$this, 'handle']);
        add_action('wp_ajax_' . SettingsAjax::AJAX_ACTION_CHECK_STATUS, [$this, 'handleCheckStatus']);
    }

    // ── 1. Test connection ────────────────────────────────────────────────────

    public function handle(): void
    {
        ['remote' => $remote, 'adminKey' => $adminKey] = $this->requireConnectionFields(SettingsAjax::AJAX_ACTION_TEST);

        // Step 1: health
        try {
            $client = ClientFactory::build($remote, $adminKey);
            $health = $client->health->retrieve();
        } catch (\Exception $e) {
            wp_send_json_error([
                'step'    => 'health',
                'message' => sprintf(
                    /* translators: %s: error message */
                    __('Could not reach server: %s', 'typesense-search'),
                    $e->getMessage()
                ),
            ]);
            return;
        }

        if (empty($health['ok'])) {
            wp_send_json_error([
                'step'    => 'health',
                'message' => __('Server responded but reported an unhealthy status.', 'typesense-search'),
            ]);
            return;
        }

        // Step 2: admin key
        try {
            $client->collections->retrieve();
        } catch (RequestUnauthorized $e) {
            wp_send_json_error([
                'step'    => 'auth',
                'message' => __('Server is reachable, but the Admin API key was rejected.', 'typesense-search'),
            ]);
            return;
        } catch (\Exception $e) {
            wp_send_json_error([
                'step'    => 'auth',
                'message' => sprintf(
                    /* translators: %s: error message */
                    __('Server is reachable, but key validation failed: %s', 'typesense-search'),
                    $e->getMessage()
                ),
            ]);
            return;
        }

        // Step 3: does the named collection exist?
        $collectionName  = sanitize_text_field(wp_unslash($_POST['collection_name'] ?? ''));
        $collectionExists = false;

        if (!empty($collectionName)) {
            $collectionExists = Collection::exists($client, $collectionName);
        }

        wp_send_json_success([
            'message'          => __('Connected successfully. Server is healthy and the Admin API key is valid.', 'typesense-search'),
            'collectionExists' => $collectionExists,
            'collectionName'   => $collectionName,
        ]);
    }

    // ── 2. Check saved-settings status ────────────────────────────────────────

    /**
     * Server-side health check using the credentials stored in WordPress options.
     *
     * Returns a structured object with three checks:
     *  - connection : can the server be reached and is it healthy?
     *  - adminKey   : does the admin key allow listing collections?
     *  - searchKey  : can the search key search the configured collection?
     *
     * When the search key check fails the response also includes `searchKeyCanFix: true`
     * so the front-end can offer a "Create new search key" button.
     */
    public function handleCheckStatus(): void
    {
        $this->requirePermission(SettingsAjax::AJAX_ACTION_CHECK_STATUS);

        $remote         = $this->settings->getRemote();
        $adminKey       = $this->settings->getAdminKey();
        $searchKey      = $this->settings->getSearchKey();
        $collectionName = $this->settings->getCollectionName();

        $result = [
            'connection'        => ['ok' => false, 'message' => ''],
            'adminKey'          => ['ok' => false, 'message' => ''],
            'collection'        => ['ok' => false, 'message' => ''],
            'searchKey'         => ['ok' => false, 'message' => ''],
            'collectionCanFix'  => false,
            'searchKeyCanFix'   => false,
        ];

        // ── 1. Connection ────────────────────────────────────────────────────

        if (empty($remote)) {
            $result['connection']['message'] = __('No host URL configured.', 'typesense-search');
            wp_send_json_success($result);
            return;
        }

        try {
            $client = ClientFactory::build($remote, $adminKey ?: 'placeholder', 5);
            $health = $client->health->retrieve();

            if (!empty($health['ok'])) {
                $result['connection']['ok']      = true;
                $result['connection']['message'] = __('Server is reachable and healthy.', 'typesense-search');
            } else {
                $result['connection']['message'] = __('Server responded but reported an unhealthy status.', 'typesense-search');
                wp_send_json_success($result);
                return;
            }
        } catch (\Exception $e) {
            $result['connection']['message'] = sprintf(
                /* translators: %s: error message */
                __('Could not reach server: %s', 'typesense-search'),
                $e->getMessage()
            );
            wp_send_json_success($result);
            return;
        }

        // ── 2. Admin key ─────────────────────────────────────────────────────

        if (empty($adminKey)) {
            $result['adminKey']['message'] = __('No admin key configured.', 'typesense-search');
        } else {
            try {
                $client = ClientFactory::build($remote, $adminKey, 5);
                $client->collections->retrieve();
                $result['adminKey']['ok']      = true;
                $result['adminKey']['message'] = __('Admin key is valid.', 'typesense-search');
            } catch (RequestUnauthorized $e) {
                $result['adminKey']['message'] = __('Admin key was rejected by the server.', 'typesense-search');
            } catch (\Exception $e) {
                $result['adminKey']['message'] = sprintf(
                    /* translators: %s: error message */
                    __('Admin key check failed: %s', 'typesense-search'),
                    $e->getMessage()
                );
            }
        }

        // ── 3. Collection existence ──────────────────────────────────────────

        if (!$result['adminKey']['ok']) {
            $result['collection']['message'] = __('Cannot check — admin key is not valid.', 'typesense-search');
        } elseif (empty($collectionName)) {
            $result['collection']['message'] = __('No collection name configured.', 'typesense-search');
        } else {
            try {
                $adminClient = ClientFactory::build($remote, $adminKey, 5);
                if (Collection::exists($adminClient, $collectionName)) {
                    $result['collection']['ok']      = true;
                    $result['collection']['message'] = sprintf(
                        /* translators: %s: collection name */
                        __('Collection "%s" exists.', 'typesense-search'),
                        $collectionName
                    );
                } else {
                    $result['collection']['message'] = sprintf(
                        /* translators: %s: collection name */
                        __('Collection "%s" does not exist.', 'typesense-search'),
                        $collectionName
                    );
                    $result['collectionCanFix'] = true;
                }
            } catch (\Exception $e) {
                $result['collection']['message'] = sprintf(
                    /* translators: %s: error message */
                    __('Collection check failed: %s', 'typesense-search'),
                    $e->getMessage()
                );
            }
        }

        // ── 4. Search key ─────────────────────────────────────────────────────

        if (empty($searchKey)) {
            $result['searchKey']['message'] = __('No search key configured.', 'typesense-search');
            $result['searchKeyCanFix']      = $result['adminKey']['ok'] && !empty($collectionName);
        } elseif (empty($collectionName)) {
            $result['searchKey']['message'] = __('No collection name configured — cannot test the search key.', 'typesense-search');
        } else {
            try {
                $searchClient = ClientFactory::build($remote, $searchKey, 5);
                $searchClient->collections[$collectionName]->documents->search([
                    'q'        => '*',
                    'query_by' => 'title',
                    'per_page' => 0,
                ]);
                $result['searchKey']['ok']      = true;
                $result['searchKey']['message'] = sprintf(
                    /* translators: %s: collection name */
                    __('Search key works against collection "%s".', 'typesense-search'),
                    $collectionName
                );
            } catch (RequestUnauthorized $e) {
                $result['searchKey']['message'] = sprintf(
                    /* translators: %s: collection name */
                    __('Search key does not have access to collection "%s".', 'typesense-search'),
                    $collectionName
                );
                // We can always create a new correctly-scoped key if the admin key works
                $result['searchKeyCanFix'] = $result['adminKey']['ok'];
            } catch (\Exception $e) {
                $result['searchKey']['message'] = sprintf(
                    /* translators: %s: error message */
                    __('Search key check failed: %s', 'typesense-search'),
                    $e->getMessage()
                );
            }
        }

        wp_send_json_success($result);
    }
}
