<?php

declare(strict_types=1);

namespace App;

/**
 * Error handling & logging.
 *
 * Timestamped, levelled lines to STDERR, kept clear of report output on STDOUT.
 */
final class Logger
{
    public function info(string $message): void
    {
        $this->write('INFO', $message);
    }

    public function error(string $message): void
    {
        $this->write('ERROR', $message);
    }

    private function write(string $level, string $message): void
    {
        $line = sprintf('[%s] %s: %s%s', $this->timestamp(), $level, $message, PHP_EOL);
        fwrite(STDERR, $line);
    }

    private function timestamp(): string
    {
        return date('Y-m-d H:i:s');
    }
}
