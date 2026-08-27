<?php

declare(strict_types=1);

namespace App;

/**
 * Configuration & secrets: WP base URL, user, application password, CA bundle.
 */
final class Config
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $user,
        private readonly string $appPassword,
        private readonly ?string $caBundlePath,
    ) {
    }

    public static function fromEnv(string $envPath): self
    {
        $env = self::parseEnvFile($envPath);

        return new self(
            baseUrl: rtrim($env['WP_BASE_URL'] ?? '', '/'),
            user: $env['WP_USER'] ?? '',
            appPassword: $env['WP_APP_PASSWORD'] ?? '',
            caBundlePath: self::resolveCaBundle(),
        );
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function user(): string
    {
        return $this->user;
    }

    public function appPassword(): string
    {
        return $this->appPassword;
    }

    public function caBundlePath(): ?string
    {
        return $this->caBundlePath;
    }

    /**
     * Parse a KEY=VALUE .env file into a map.
     *
     * @return array<string, string>
     */
    private static function parseEnvFile(string $envPath): array
    {
        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $env = [];
        foreach ($lines as $line) {
            $pair = self::parseEnvLine($line);
            if ($pair !== null) {
                [$key, $value] = $pair;
                $env[$key] = $value;
            }
        }

        return $env;
    }

    /**
     * One line to a [key, value] pair, or null if blank/comment/malformed.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function parseEnvLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            return null;
        }

        [$key, $value] = explode('=', $line, 2);

        return [trim($key), trim($value)];
    }

    /**
     * Project-local cacert.pem, used only when php.ini has no CA bundle
     * (the case on Windows PHP).
     */
    private static function resolveCaBundle(): ?string
    {
        if (self::phpIniHasCaBundle()) {
            return null;
        }

        $local = dirname(__DIR__) . '/cacert.pem';

        return is_readable($local) ? $local : null;
    }

    private static function phpIniHasCaBundle(): bool
    {
        return ini_get('curl.cainfo') !== '' || ini_get('openssl.cafile') !== '';
    }
}
