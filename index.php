<?php

declare(strict_types=1);

/**
 * Entry point.
 *
 * Wires the four pipeline stages together:
 *   authenticated access -> fetch -> analyse -> report.
 */

use App\Analyser;
use App\Config;
use App\DataFetcher;
use App\Logger;
use App\ReportBuilder;
use App\WordPressClient;

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Dependencies not installed - run `composer install` first." . PHP_EOL);
    exit(1);
}
require $autoload;

$logger = new Logger();

try {
    $config   = Config::fromEnv(__DIR__ . '/.env');
    $client   = new WordPressClient($config, $logger);
    $fetcher  = new DataFetcher($client, $logger);

    $items    = array_merge($fetcher->fetchPosts(), $fetcher->fetchPages());
    $analysis = (new Analyser($logger))->analyse($items);
    (new ReportBuilder())->build($analysis, __DIR__ . '/output');

    $logger->info('Done. Reports written to output/.');
} catch (\Throwable $e) {
    $logger->error($e->getMessage());
    exit(1);
}
