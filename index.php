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

require __DIR__ . '/vendor/autoload.php';

// TODO: Run the pipeline.
