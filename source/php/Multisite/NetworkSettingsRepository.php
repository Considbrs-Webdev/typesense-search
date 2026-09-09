<?php

namespace TypesenseSearch\Multisite;

/** Network ownership and runtime eligibility, evaluated in the current site context. */
class NetworkSettingsRepository
{
    public const REMOTE = 'typesense_network_remote';
    public const ADMIN_KEY = 'typesense_network_admin_key';
    public const FRONTEND_HOST = 'typesense_network_frontend_host';
    public const ENABLED = 'typesense_network_enabled_sites';
    public const STATE = 'typesense_network_state';
    public const LOCK = 'typesense_network_provision_lock';

    /** Only a privileged synchronous provisioning operation sets this context. */
    private static ?array $candidateContext = null;

    public function networkId(): int
    {
        return (int) (get_site(get_current_blog_id())->network_id ?? get_current_network_id());
    }

    public function isNetworkActivated(): bool
    {
        if (!is_multisite()) {
            return false;
        }
        $basename = defined('TYPESENSESEARCH_BASENAME') ? TYPESENSESEARCH_BASENAME : 'typesense-search/typesense-search.php';
        return isset(((array) get_network_option($this->networkId(), 'active_sitewide_plugins', []))[$basename]);
    }

    public function selected(): bool
    {
        $site = get_site(get_current_blog_id());
        return $site && !$site->archived && !$site->spam && !$site->deleted
            && in_array((int) get_current_blog_id(), array_map('intval', (array) get_network_option($this->networkId(), self::ENABLED, [])), true);
    }

    public function conflict(): bool
    {
        return $this->constant('TYPESENSE_COLLECTION') !== '' || $this->constant('TYPESENSE_SEARCH_KEY') !== '';
    }

    public function constant(string $name): string
    {
        return defined($name) && is_string(constant($name)) ? constant($name) : '';
    }

    public function connection(): array
    {
        return [
            'remote' => $this->constant('TYPESENSE_HOST') ?: (string) get_network_option($this->networkId(), self::REMOTE, ''),
            'admin_key' => $this->constant('TYPESENSE_ADMIN_KEY') ?: (string) get_network_option($this->networkId(), self::ADMIN_KEY, ''),
            'frontend_host' => $this->constant('TYPESENSE_FRONTEND_HOST') ?: (string) get_network_option($this->networkId(), self::FRONTEND_HOST, ''),
        ];
    }

    public function identity(): array
    {
        return (new CollectionNameResolver())->identity() + [
            'remote' => rtrim($this->connection()['remote'], '/'),
            'network' => $this->networkId(),
            'site' => (int) get_current_blog_id(),
        ];
    }

    public function state(): array
    {
        return (array) get_option(self::STATE, []);
    }

    public function mapping(): array
    {
        if (self::$candidateContext !== null && self::$candidateContext['site'] === get_current_blog_id()) {
            return self::$candidateContext['mapping'];
        }
        return (array) ($this->state()['active'] ?? []);
    }

    public function canUse(): bool
    {
        if (!$this->isNetworkActivated()) {
            return true;
        }
        $connection = $this->connection();
        if (!$this->selected() || $this->conflict() || $connection['remote'] === '' || $connection['admin_key'] === '') {
            return false;
        }
        $mapping = $this->mapping();
        return ($mapping['identity'] ?? null) === $this->identity()
            && !empty($mapping['collection']) && !empty($mapping['key']);
    }

    /** Explain configuration eligibility independently of server/index availability. */
    public function unavailableReason(): string
    {
        if (!$this->selected()) {
            return __('This site is disabled. Select it in Network Admin to enable Typesense.', 'typesense-search');
        }
        if ($this->conflict()) {
            return __('Global collection or search-key constants conflict with network mode. Remove these constants before setup.', 'typesense-search');
        }
        $connection = $this->connection();
        if ($connection['remote'] === '' || $connection['admin_key'] === '') {
            return __('Configure the network connection first.', 'typesense-search');
        }
        $mapping = $this->mapping();
        if (!empty($mapping['identity']) && $mapping['identity'] !== $this->identity()) {
            return __('The site URL, environment or server has changed. Save the site selection to run setup again.', 'typesense-search');
        }
        return __('Setup is incomplete. Save the site selection or retry the failed setup.', 'typesense-search');
    }

    /** Runs only server-owned preparation/indexing code, never selected by public request input. */
    public function withCandidate(array $mapping, callable $operation): mixed
    {
        $previous = self::$candidateContext;
        self::$candidateContext = ['site' => get_current_blog_id(), 'mapping' => $mapping];
        try {
            return $operation();
        } finally {
            self::$candidateContext = $previous;
        }
    }
}
