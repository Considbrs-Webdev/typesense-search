<?php

namespace TypesenseSearch\Indexing;

use TypesenseSearch\Indexing\Contracts\ExternalIndexingStrategyInterface;
use TypesenseSearch\Indexing\Contracts\IndexingStrategyInterface;

/**
 * Class IndexingRegistry
 *
 * Central registry that holds all registered indexing strategies. Other
 * components (IndexingHooks, CLI commands, etc.) use this registry to
 * discover the correct strategy for any given post.
 *
 * The registry holds two separate sets of strategies:
 *
 *   WordPress strategies  (IndexingStrategyInterface)
 *     — event-driven; wired to post lifecycle hooks; resolved via resolve().
 *     — Register with: $registry->register(new PostIndexingStrategy())
 *
 *   External strategies   (ExternalIndexingStrategyInterface)
 *     — pull-driven; fetch content from outside WordPress; triggered by cron,
 *       CLI, or any other explicit call.
 *     — Register with: $registry->registerExternal(new EServiceIndexingStrategy())
 *     — Run a sync:    $registry->runExternalSync('eservice')
 *     — Run all syncs: $registry->runAllExternalSyncs()
 *
 * WordPress strategies are evaluated in registration order — register more
 * specific strategies (e.g. PDF) before generic ones (e.g. Post).
 *
 * @package TypesenseSearch\Indexing
 */
class IndexingRegistry
{
    /**
     * WordPress-content strategies, keyed by identifier.
     *
     * @var IndexingStrategyInterface[]
     */
    private array $strategies = [];

    /**
     * External-content strategies, keyed by identifier.
     *
     * @var ExternalIndexingStrategyInterface[]
     */
    private array $externalStrategies = [];

    /**
     * Register a new indexing strategy.
     *
     * Strategies are evaluated in registration order — register more specific
     * strategies (e.g. PDF) before generic ones (e.g. Post) so they match
     * first.
     *
     * @param IndexingStrategyInterface $strategy
     * @return self
     */
    public function register(IndexingStrategyInterface $strategy): self
    {
        $this->strategies[$strategy->getIdentifier()] = $strategy;

        return $this;
    }

    /**
     * Resolve the correct strategy for a given post.
     *
     * Returns the first registered strategy whose supports() method returns
     * true, or null if no strategy handles the content.
     *
     * @param \WP_Post $post
     * @return IndexingStrategyInterface|null
     */
    public function resolve(\WP_Post $post): ?IndexingStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($post)) {
                return $strategy;
            }
        }

        return null;
    }

    /**
     * Retrieve a strategy by its identifier.
     *
     * @param string $identifier
     * @return IndexingStrategyInterface|null
     */
    public function get(string $identifier): ?IndexingStrategyInterface
    {
        return $this->strategies[$identifier] ?? null;
    }

    /**
     * Return all registered strategies.
     *
     * @return IndexingStrategyInterface[]
     */
    public function all(): array
    {
        return $this->strategies;
    }

    // ── External strategies ────────────────────────────────────────────────

    /**
     * Register an external indexing strategy.
     *
     * External strategies pull content from outside WordPress (APIs, feeds,
     * etc.) and are triggered explicitly (cron, CLI) rather than by post
     * lifecycle events.
     *
     * @param ExternalIndexingStrategyInterface $strategy
     * @return self
     */
    public function registerExternal(ExternalIndexingStrategyInterface $strategy): self
    {
        $this->externalStrategies[$strategy->getIdentifier()] = $strategy;

        return $this;
    }

    /**
     * Retrieve an external strategy by its identifier.
     *
     * @param string $identifier
     * @return ExternalIndexingStrategyInterface|null
     */
    public function getExternal(string $identifier): ?ExternalIndexingStrategyInterface
    {
        return $this->externalStrategies[$identifier] ?? null;
    }

    /**
     * Return all registered external strategies.
     *
     * @return ExternalIndexingStrategyInterface[]
     */
    public function allExternal(): array
    {
        return $this->externalStrategies;
    }

    /**
     * Run syncAll() on a single external strategy by identifier.
     *
     * Returns the number of items indexed, or -1 when the identifier is not
     * registered.
     *
     * @param string $identifier Strategy identifier (e.g. 'eservice').
     * @return int
     */
    public function runExternalSync(string $identifier): int
    {
        $strategy = $this->getExternal($identifier);

        if ($strategy === null) {
            return -1;
        }

        return $strategy->syncAll();
    }

    /**
     * Run syncAll() on every registered external strategy.
     *
     * Returns an associative array of [ identifier => count ] with the number
     * of items indexed per strategy.
     *
     * @return array<string, int>
     */
    public function runAllExternalSyncs(): array
    {
        $results = [];

        foreach ($this->externalStrategies as $identifier => $strategy) {
            $results[$identifier] = $strategy->syncAll();
        }

        return $results;
    }

    // ── Hooks ──────────────────────────────────────────────────────────────

    /**
     * Register hooks for all strategies — both WordPress and external.
     *
     * Called once during bootstrap to let each strategy wire up its
     * content-specific WordPress hooks (post lifecycle events, cron events,
     * admin actions, etc.).
     *
     * @return void
     */
    public function registerAllHooks(): void
    {
        foreach ($this->strategies as $strategy) {
            $strategy->registerHooks();
        }

        foreach ($this->externalStrategies as $strategy) {
            $strategy->registerHooks();
        }
    }
}
