<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Authenticated access to the WordPress REST API.
 *
 * One GET primitive, authenticated with HTTP Basic Auth (application password).
 */
class WordPressClient
{
    private const API_PREFIX = '/wp-json';
    private const TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger,
    ) {
    }

    /**
     * Authenticated GET returning the decoded JSON body.
     *
     * @param string               $path  REST route, e.g. "/wp/v2/posts".
     * @param array<string, mixed> $query Query parameters to append.
     * @return array<mixed> Decoded JSON response.
     */
    public function get(string $path, array $query = []): array
    {
        return $this->getWithHeaders($path, $query)['body'];
    }

    /**
     * Authenticated GET returning the decoded body plus response headers.
     *
     * Used for paginated routes that report the page count in headers
     * (e.g. X-WP-TotalPages).
     *
     * @param array<string, mixed> $query
     * @return array{body: array<mixed>, headers: array<string, string>}
     */
    public function getWithHeaders(string $path, array $query = []): array
    {
        $url = $this->buildUrl($path, $query);

        [$rawHeaders, $body, $status] = $this->execute($url);

        $this->logger->info(sprintf('GET %s -> %d', $path, $status));
        $this->guardStatus($status, $path, $body);

        return [
            'body'    => $this->decode($body, $path),
            'headers' => $this->parseHeaders($rawHeaders),
        ];
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
     * Run the request, returning [rawHeaders, body, httpStatus].
     *
     * @return array{0: string, 1: string, 2: int}
     */
    private function execute(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, $this->curlOptions());

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);

        if ($response === false) {
            $this->fail('cURL error requesting ' . $url . ': ' . $error);
        }

        $rawHeaders = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        return [$rawHeaders, $body, $status];
    }

    /**
     * @return array<int, mixed>
     */
    private function curlOptions(): array
    {
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPGET        => true,
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

    /**
     * Parse raw response headers into a lower-cased name => value map.
     *
     * @return array<string, string>
     */
    private function parseHeaders(string $rawHeaders): array
    {
        $headers = [];
        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return $headers;
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
