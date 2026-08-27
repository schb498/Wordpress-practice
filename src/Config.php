<?php

declare(strict_types=1);

namespace App;

/**
 * Configuration & secrets handling.
 *
 * Loads settings (WP base URL, user, application password) and resolves the
 * CA bundle. Cross-cutting: feeds the rest of the pipeline.
 */
final class Config
{
    // TODO: constructor / properties (baseUrl, user, appPassword, caBundlePath).

    public static function fromEnv(string $envPath): self
    {
        // TODO: parse the .env file and build a Config.
    }

    public function baseUrl(): string
    {
        // TODO
    }

    public function user(): string
    {
        // TODO
    }

    public function appPassword(): string
    {
        // TODO
    }

    public function caBundlePath(): ?string
    {
        // TODO
    }
}
