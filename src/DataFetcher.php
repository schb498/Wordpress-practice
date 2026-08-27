<?php

declare(strict_types=1);

namespace App;

/**
 * Stage 2: Data fetching (posts and pages).
 */
final class DataFetcher
{
    public function __construct(
        private readonly WordPressClient $client,
        private readonly Logger $logger,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchPosts(): array
    {
        // TODO: fetch posts via the client (paginate).
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchPages(): array
    {
        // TODO: fetch pages via the client (paginate).
    }
}
