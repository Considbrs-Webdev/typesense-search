<?php

namespace TypesenseSearch\Indexing\Contracts;

use TypesenseSearch\Indexing\IndexableDocument;

/**
 * Interface IndexingStrategyInterface
 *
 * Defines the contract for all indexing strategies. Each strategy knows how
 * to determine eligibility, build a Typesense document, and perform
 * index/deindex operations for a specific type of content.
 *
 * Strategies register themselves with the IndexingRegistry and declare
 * which WordPress hooks they need via registerHooks().
 *
 * @package TypesenseSearch\Indexing\Contracts
 */
interface IndexingStrategyInterface
{
    /**
     * Return a unique identifier for this strategy (e.g. 'post', 'pdf').
     *
     * Used by the registry to look up strategies and by logging/debugging.
     *
     * @return string
     */
    public function getIdentifier(): string;

    /**
     * Determine whether the given post should be indexed by this strategy.
     *
     * @param \WP_Post $post The post to evaluate.
     * @return bool
     */
    public function shouldIndex(\WP_Post $post): bool;

    /**
     * Build an IndexableDocument from a WP_Post object.
     *
     * Returns false when the document cannot be built (e.g. missing file,
     * extraction failure). The returned object is passed directly to
     * AbstractIndexingStrategy::index() which calls toArray() before upserting.
     *
     * @param \WP_Post $post The post to build the document for.
     * @return IndexableDocument|false
     */
    public function buildDocument(\WP_Post $post): IndexableDocument|false;

    /**
     * Upsert the document for the given post into Typesense.
     *
     * @param \WP_Post $post The post to index.
     * @return bool True on success, false on failure.
     */
    public function index(\WP_Post $post): bool;

    /**
     * Remove a document from the Typesense index by post ID.
     *
     * @param int $postId The WordPress post ID to deindex.
     * @return bool True on success (or already absent), false on error.
     */
    public function deindex(int $postId): bool;

    /**
     * Register any WordPress hooks this strategy needs.
     *
     * Called once during bootstrap. Strategies use this to wire up any
     * content-specific lifecycle events (e.g. attachment hooks for PDFs).
     *
     * @return void
     */
    public function registerHooks(): void;

    /**
     * Determine whether this strategy can handle the given post.
     *
     * Used by the registry to route a post to the correct strategy.
     * Unlike shouldIndex(), this checks whether the strategy is the right
     * *type* of handler for the content — not whether the content qualifies
     * for indexing.
     *
     * @param \WP_Post $post The post to check.
     * @return bool
     */
    public function supports(\WP_Post $post): bool;
}
