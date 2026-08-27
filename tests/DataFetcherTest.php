<?php

declare(strict_types=1);

namespace App\Tests;

use App\DataFetcher;
use App\Logger;
use App\WordPressClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The WordPressClient is mocked, so these tests drive that loop directly with
 * scripted responses — no cURL, no network.
 */
#[CoversClass(DataFetcher::class)]
final class DataFetcherTest extends TestCase
{
    /**
     * Walks every page: page 1 reports 3 total pages, so the fetcher must
     * request pages 1, 2 and 3 and concatenate their items in order — and
     * then stop (no page 4). Missing content here would silently shrink the
     * audit, so this is the test that matters most.
     */
    #[Test]
    public function it_follows_pagination_across_all_pages_and_concatenates_items(): void
    {
        $client = $this->createMock(WordPressClient::class);

        $client->expects($this->exactly(3))
            ->method('getWithHeaders')
            ->willReturnCallback(function (string $path, array $query): array {
                $this->assertSame('/wp/v2/posts', $path);

                return match ($query['page']) {
                    1 => $this->response([['id' => 1], ['id' => 2]], totalPages: 3),
                    2 => $this->response([['id' => 3]], totalPages: 3),
                    3 => $this->response([['id' => 4], ['id' => 5]], totalPages: 3),
                    default => $this->fail("Unexpected page {$query['page']}"),
                };
            });

        $items = (new DataFetcher($client, new Logger()))->fetchPosts();

        $this->assertSame([1, 2, 3, 4, 5], array_column($items, 'id'));
    }

    /**
     * Each request must carry status=any (so drafts and private items are
     * included — the whole point of the audit) and the _fields allowlist the
     * rules need. A regression here would silently skip draft content.
     */
    #[Test]
    public function it_requests_all_statuses_and_the_fields_the_rules_need(): void
    {
        $client = $this->createMock(WordPressClient::class);
        $client->method('getWithHeaders')
            ->willReturnCallback(function (string $path, array $query): array {
                $this->assertSame('any', $query['status']);
                $this->assertSame(100, $query['per_page']);
                $this->assertStringContainsString('featured_media', $query['_fields']);
                $this->assertStringContainsString('modified', $query['_fields']);

                return $this->response([], totalPages: 1);
            });

        (new DataFetcher($client, new Logger()))->fetchPages();
    }

    /**
     * With no X-WP-TotalPages header, totalPages falls back to 1: a single
     * request, and an empty body yields an empty result rather than a crash.
     */
    #[Test]
    public function it_makes_a_single_request_when_there_is_only_one_page(): void
    {
        $client = $this->createMock(WordPressClient::class);
        $client->expects($this->once())
            ->method('getWithHeaders')
            ->willReturn(['body' => [], 'headers' => []]);

        $items = (new DataFetcher($client, new Logger()))->fetchPosts();

        $this->assertSame([], $items);
    }

    /**
     * A client's getWithHeaders() return value: body plus the pagination header.
     *
     * @param array<int, array<string, mixed>> $body
     * @return array{body: array<int, array<string, mixed>>, headers: array<string, string>}
     */
    private function response(array $body, int $totalPages): array
    {
        return [
            'body'    => $body,
            'headers' => ['x-wp-totalpages' => (string) $totalPages],
        ];
    }
}
