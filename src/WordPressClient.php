<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Authenticated access to the WordPress REST API.
 *
 * One GET primitive, authenticated with HTTP Basic Auth (application password).
 */
final class WordPressClient
{
    private const API_PREFIX = '/wp-json';
    private const TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger,
    ) {
    }

    /**
     * Perform an authenticated GET against a REST route.
     *
     * @param string               $path  REST route, e.g. "/wp/v2/posts".
     * @param array<string, mixed> $query Query parameters to append.
     * @return array<mixed> Decoded JSON response.
     */
    public function get(string $path, array $query = []): array
    {
        $url = $this->buildUrl($path, $query);

        [$body, $status] = $this->execute($url);

        $this->logger->info(sprintf('GET %s -> %d', $path, $status));
        $this->guardStatus($status, $path, $body);

        return $this->decode($body, $path);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function buildUrl(string $path, array $query): string
    {
        $url = $this->config->baseUrl() . self::API_PREFIX . '/' . ltrim($path, '/');

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    /**
     * Run the request, returning [body, httpStatus].
     *
     * @return array{0: string, 1: int}
     */
    private function execute(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, $this->curlOptions());

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($body === false) {
            $this->fail('cURL error requesting ' . $url . ': ' . $error);
        }

        return [$body, $status];
    }

    /**
     * @return array<int, mixed>
     */
    private function curlOptions(): array
    {
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => $this->credentials(),
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
        ];

        // Use the project CA bundle when php.ini has none (Windows).
        $caBundle = $this->config->caBundlePath();
        if ($caBundle !== null) {
            $options[CURLOPT_CAINFO] = $caBundle;
        }

        return $options;
    }

    private function credentials(): string
    {
        return $this->config->user() . ':' . $this->config->appPassword();
    }

    private function guardStatus(int $status, string $path, string $body): void
    {
        if ($status !== 200) {
            $this->fail(sprintf('HTTP %d for %s: %s', $status, $path, $this->snippet($body)));
        }
    }

    /**
     * @return array<mixed>
     */
    private function decode(string $body, string $path): array
    {
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            $this->fail(sprintf('Invalid JSON for %s: %s', $path, $this->snippet($body)));
        }

        return $decoded;
    }

    private function snippet(string $body, int $length = 200): string
    {
        $body = trim($body);

        return strlen($body) > $length ? substr($body, 0, $length) . '…' : $body;
    }

    /**
     * Log and throw.
     *
     * @return never
     */
    private function fail(string $message): void
    {
        $this->logger->error($message);
        throw new RuntimeException($message);
    }
}
