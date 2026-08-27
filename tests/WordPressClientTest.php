<?php

declare(strict_types=1);

/*
 * Defining curl_*() in namespace App shadows the global ones for the client's
 * unqualified calls, so tests stub the network. CurlStub holds the state.
 */

namespace App {

    /** Shared state for the cURL override functions below. */
    final class CurlStub
    {
        /** The URL passed to curl_init(). */
        public static ?string $url = null;

        /** Options passed to curl_setopt_array(), keyed by CURLOPT_* constant. */
        public static array $options = [];

        /** Raw string curl_exec() should return, or false to simulate failure. */
        public static string|false $response = '';

        /** curl_getinfo() values, keyed by CURLINFO_* constant. */
        public static array $info = [];

        /** curl_error() message when a request "fails". */
        public static string $error = '';

        public static function reset(): void
        {
            self::$url = null;
            self::$options = [];
            self::$response = '';
            self::$info = [];
            self::$error = '';
        }
    }

    function curl_init(?string $url = null): \CurlHandle
    {
        CurlStub::$url = $url;

        // A real handle keeps type hints happy; we never actually use it.
        return \curl_init($url);
    }

    function curl_setopt_array(\CurlHandle $handle, array $options): bool
    {
        CurlStub::$options = $options;

        return true;
    }

    function curl_exec(\CurlHandle $handle): string|false
    {
        return CurlStub::$response;
    }

    function curl_getinfo(\CurlHandle $handle, ?int $option = null): mixed
    {
        return CurlStub::$info[$option] ?? 0;
    }

    function curl_error(\CurlHandle $handle): string
    {
        return CurlStub::$error;
    }
}

namespace App\Tests {

    use App\Config;
    use App\CurlStub;
    use App\Logger;
    use App\WordPressClient;
    use PHPUnit\Framework\Attributes\CoversClass;
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;
    use RuntimeException;

    /**
     * Tests for the one HTTP primitive: authenticated GET.
     */
    #[CoversClass(WordPressClient::class)]
    final class WordPressClientTest extends TestCase
    {
        protected function setUp(): void
        {
            CurlStub::reset();
        }

        /**
         * A raw HTTP response (headers + body) as curl_exec() returns it when
         * CURLOPT_HEADER is on, and the matching curl_getinfo() metadata.
         */
        private function primeResponse(string $headers, string $body, int $status = 200): void
        {
            $raw = $headers . "\r\n\r\n" . $body;
            CurlStub::$response = $raw;
            CurlStub::$info = [
                CURLINFO_HTTP_CODE   => $status,
                CURLINFO_HEADER_SIZE => strlen($headers . "\r\n\r\n"),
            ];
        }

        private function client(
            string $baseUrl = 'https://example.com',
            string $user = 'editor',
            string $password = 'secret pass',
        ): WordPressClient {
            $config = new Config($baseUrl, $user, $password, null);

            return new WordPressClient($config, new Logger());
        }

        /**
         * AUTH: the request carries HTTP Basic Auth with the configured
         * user:application-password, and asks for JSON.
         */
        #[Test]
        public function it_sends_basic_auth_credentials_from_config(): void
        {
            $this->primeResponse('HTTP/1.1 200 OK', '[]');

            $this->client(user: 'editor', password: 'app pass word')
                ->get('/wp/v2/posts');

            $options = CurlStub::$options;
            $this->assertSame(CURLAUTH_BASIC, $options[CURLOPT_HTTPAUTH]);
            $this->assertSame('editor:app pass word', $options[CURLOPT_USERPWD]);
            $this->assertContains('Accept: application/json', $options[CURLOPT_HTTPHEADER]);
        }

        /**
         * GET: the URL is built from base + /wp-json + path, query params are
         * appended, and the decoded JSON body is returned to the caller.
         */
        #[Test]
        public function it_builds_the_url_with_query_and_returns_the_decoded_body(): void
        {
            $this->primeResponse('HTTP/1.1 200 OK', '[{"id":1},{"id":2}]');

            $body = $this->client(baseUrl: 'https://example.com')
                ->get('/wp/v2/posts', ['per_page' => 100, 'page' => 2]);

            // Reads back the URL cURL was told to fetch (passed to curl_init).
            $this->assertSame(
                'https://example.com/wp-json/wp/v2/posts?per_page=100&page=2',
                CurlStub::$url,
            );
            $this->assertSame([['id' => 1], ['id' => 2]], $body);
        }

        /**
         * GET (with headers): response headers come back lower-cased and
         * trimmed, so DataFetcher can read X-WP-TotalPages for pagination.
         */
        #[Test]
        public function it_returns_parsed_response_headers_alongside_the_body(): void
        {
            $this->primeResponse(
                "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nX-WP-TotalPages: 3",
                '[]',
            );

            $result = $this->client()->getWithHeaders('/wp/v2/posts');

            $this->assertSame([], $result['body']);
            $this->assertSame('3', $result['headers']['x-wp-totalpages']);
            $this->assertSame('application/json', $result['headers']['content-type']);
        }

        /** A non-200 status is turned into a RuntimeException naming the path. */
        #[Test]
        public function it_throws_on_a_non_200_response(): void
        {
            $this->primeResponse('HTTP/1.1 401 Unauthorized', '{"code":"unauthorized"}', 401);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('HTTP 401 for /wp/v2/posts');

            $this->client()->get('/wp/v2/posts');
        }

        /** A 200 with a non-JSON body is rejected rather than silently passed on. */
        #[Test]
        public function it_throws_when_the_body_is_not_valid_json(): void
        {
            $this->primeResponse('HTTP/1.1 200 OK', '<html>not json</html>');

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Invalid JSON for /wp/v2/posts');

            $this->client()->get('/wp/v2/posts');
        }
    }
}
