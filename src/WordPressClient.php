<?php

declare(strict_types=1);

namespace App;

/**
 * Authenticated access to the WordPress REST API.
 */
final class WordPressClient
{
    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger,
    ) {
    }

    /**
     * Perform an authenticated GET against a REST route.
     *
     * @param array<string, mixed> $query
     * @return array<mixed>
     */
    public function get(string $path, array $query = []): array
    {
        // TODO: authenticated curl request, return decoded JSON.
    }
}
