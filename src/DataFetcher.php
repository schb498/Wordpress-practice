<?php

declare(strict_types=1);

namespace App;

/**
 * Stage 2: Data fetching (posts and pages).
 *
 * Pulls every item across all pages, including drafts and private content
 * (status=any), so later stages see what the audit rules check against.
 */
final class DataFetcher
{
    private const PER_PAGE = 100;

    /** Fields the audit rules need from each item. */
    private const FIELDS = 'id,type,status,title,content,excerpt,modified,featured_media';

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
        return $this->fetchAll('/wp/v2/posts');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchPages(): array
    {
        return $this->fetchAll('/wp/v2/pages');
    }

    /**
     * Fetch every item from a paginated route.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchAll(string $path): array
    {
        $items = [];
        $page = 1;
        $totalPages = 1;

        do {
            [$batch, $totalPages] = $this->fetchPage($path, $page);
            array_push($items, ...$batch);
            $page++;
        } while ($page <= $totalPages);

        $this->logger->info(sprintf('Fetched %d items from %s', count($items), $path));

        return $items;
    }

    /**
     * Fetch a single page and read the total page count from the headers.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function fetchPage(string $path, int $page): array
    {
        $response = $this->client->getWithHeaders($path, $this->pageQuery($page));

        /** @var array<int, array<string, mixed>> $items */
        $items = $response['body'];
        $totalPages = $this->totalPages($response['headers']);

        return [$items, $totalPages];
    }

    /**
     * @return array<string, mixed>
     */
    private function pageQuery(int $page): array
    {
        return [
            'status'   => 'any',
            'per_page' => self::PER_PAGE,
            'page'     => $page,
            '_fields'  => self::FIELDS,
        ];
    }

    /**
     * @param array<string, string> $headers
     */
    private function totalPages(array $headers): int
    {
        return max(1, (int) ($headers['x-wp-totalpages'] ?? 1));
    }
}
