<?php

declare(strict_types=1);

namespace TypesenseSearch\Tests\Integration\Support;

use TypesenseSearch\Indexing\DocumentBuilder;

/**
 * Poll Typesense until indexed documents match DocumentBuilder (no fixed sleeps).
 */
final class IndexWaiter
{
    public function __construct(
        private readonly \Typesense\Client $client,
        private readonly string $collectionName,
        private readonly int $timeoutMs = 5000,
        private readonly int $intervalMs = 50
    ) {
    }

    /**
     * @param int[] $postIds
     */
    public function waitUntilPostsMatch(array $postIds): void
    {
        $deadline = (int) (microtime(true) * 1000) + $this->timeoutMs;

        while (true) {
            $pending = [];
            foreach ($postIds as $postId) {
                $postId = (int) $postId;
                $post = get_post($postId);
                if (!$post) {
                    continue;
                }

                $indexed = $this->fetchDocument((string) $postId);
                $diff = IndexDiff::diffPost($indexed, $post);
                if ($diff !== []) {
                    $pending[$postId] = $diff;
                }
            }

            if ($pending === []) {
                return;
            }

            if ((int) (microtime(true) * 1000) >= $deadline) {
                $lines = [];
                foreach ($pending as $id => $diff) {
                    $lines[] = 'Post ' . $id . ':';
                    foreach ($diff as $line) {
                        $lines[] = '  ' . $line;
                    }
                }
                throw new \RuntimeException(
                    "Index did not match database within {$this->timeoutMs}ms:\n" . implode("\n", $lines)
                );
            }

            usleep($this->intervalMs * 1000);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchDocument(string $documentId): ?array
    {
        try {
            return $this->client->collections[$this->collectionName]->documents[$documentId]->retrieve();
        } catch (\Typesense\Exceptions\ObjectNotFound) {
            return null;
        }
    }

    /**
     * Convenience: wait until a single post matches or fail with readable diff.
     */
    public function assertPostMatches(int $postId): void
    {
        $this->waitUntilPostsMatch([$postId]);
    }

    /**
     * Return expected document from DB for debugging.
     *
     * @return array<string, mixed>
     */
    public static function expectedDocument(int $postId): array
    {
        $post = get_post($postId);
        if (!$post) {
            throw new \InvalidArgumentException('Post not found: ' . $postId);
        }

        return DocumentBuilder::build($post)->toArray();
    }
}
