<?php

declare(strict_types=1);

namespace App;

/**
 * Output/reporting.
 *
 * Renders the analysis into two reports written to the output/ directory:
 *   - output/report.json  (machine-readable)
 *   - output/report.html  (human-readable)
 *
 * Outputs include no. of items checked and no. of issues found per audit rule.
 */
final class ReportBuilder
{
    /**
     * Build both reports (JSON + HTML) from the analysis and write them to disk.
     *
     * @param array<string, mixed> $analysis
     * @param string $outputDir Directory to write report.json / report.html into.
     */
    public function build(array $analysis, string $outputDir): void
    {
        // TODO: summarise, then write the JSON and HTML reports.
    }

    /**
     * Summary of the run: items checked and issue count per rule.
     *
     * @param array<string, mixed> $analysis
     * @return array<string, mixed> e.g. ['itemsChecked' => int, 'issuesByRule' => ['R1' => int, ...]]
     */
    public function summarise(array $analysis): array
    {
        // TODO: count items checked and tally issues by rule.
    }

    /**
     * Write the machine-readable JSON report to output/report.json.
     *
     * @param array<string, mixed> $analysis
     * @param array<string, mixed> $summary
     */
    public function writeJson(array $analysis, array $summary, string $path): void
    {
        // TODO: encode analysis + summary as JSON and write to $path.
    }

    /**
     * Write the human-readable HTML report to output/report.html.
     *
     * @param array<string, mixed> $analysis
     * @param array<string, mixed> $summary
     */
    public function writeHtml(array $analysis, array $summary, string $path): void
    {
        // TODO: render analysis + summary as HTML and write to $path.
    }
}
