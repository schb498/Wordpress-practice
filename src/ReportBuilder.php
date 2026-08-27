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
    private const JSON_FILE = 'report.json';
    private const HTML_FILE = 'report.html';

    /**
     * Build both reports (JSON + HTML) from the analysis and write them to disk.
     *
     * @param array<string, mixed> $analysis
     * @param string $outputDir Directory to write report.json / report.html into.
     */
    public function build(array $analysis, string $outputDir): void
    {
        $summary = $this->summarise($analysis);
        $this->ensureDir($outputDir);

        $this->writeJson($analysis, $summary, $outputDir . '/' . self::JSON_FILE);
        $this->writeHtml($analysis, $summary, $outputDir . '/' . self::HTML_FILE);
    }

    /**
     * Summary of the run: items checked, issue count per rule, and their total.
     *
     * @param array<string, mixed> $analysis
     * @return array<string, mixed> e.g. ['itemsChecked' => int, 'issuesByRule' => ['R1' => int, ...]]
     */
    public function summarise(array $analysis): array
    {
        $issuesByRule = $analysis['issuesByRule'] ?? [];

        return [
            'itemsChecked' => $analysis['itemsChecked'] ?? 0,
            'issuesByRule' => $issuesByRule,
            'totalIssues'  => array_sum($issuesByRule),
        ];
    }

    /**
     * Write the machine-readable JSON report to output/report.json.
     *
     * @param array<string, mixed> $analysis
     * @param array<string, mixed> $summary
     */
    public function writeJson(array $analysis, array $summary, string $path): void
    {
        $payload = ['summary' => $summary, 'analysis' => $analysis];
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        file_put_contents($path, json_encode($payload, $flags) . PHP_EOL);
    }

    /**
     * Write the human-readable HTML report to output/report.html.
     *
     * @param array<string, mixed> $analysis
     * @param array<string, mixed> $summary
     */
    public function writeHtml(array $analysis, array $summary, string $path): void
    {
        $rules = $analysis['rules'] ?? [];
        $items = $analysis['items'] ?? [];

        $body = $this->renderSummaryTable($summary, $rules)
            . $this->renderItemsTable($items, $rules);

        file_put_contents($path, $this->renderPage($body));
    }

    // --- HTML helpers ------------------------------------------------------

    /** Wrap the report body in a self-contained HTML page. */
    private function renderPage(string $body): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
              <meta charset="utf-8">
              <title>WordPress Content Analysis</title>
              <style>
                body { font: 15px/1.5 system-ui, sans-serif; margin: 2rem; color: #222; }
                h1 { font-size: 1.4rem; }
                h2 { font-size: 1.1rem; margin-top: 2rem; }
                table { border-collapse: collapse; margin-top: .5rem; }
                th, td { border: 1px solid #ddd; padding: .4rem .7rem; text-align: left; }
                th { background: #f4f4f4; }
                .id { font-family: monospace; }
                .muted { color: #777; }
              </style>
            </head>
            <body>
            <h1>WordPress Content Analysis</h1>
            $body
            </body>
            </html>

            HTML;
    }

    /**
     * Summary table: items checked, total issues, and a count per rule.
     *
     * @param array<string, mixed>  $summary
     * @param array<string, string> $rules
     */
    private function renderSummaryTable(array $summary, array $rules): string
    {
        $issuesByRule = $summary['issuesByRule'] ?? [];

        $rows = '';
        foreach ($rules as $ruleId => $name) {
            $rows .= $this->row([
                $this->esc($ruleId),
                $this->esc($name),
                (string) ($issuesByRule[$ruleId] ?? 0),
            ]);
        }

        return '<h2>Summary</h2>'
            . '<p class="muted">Items checked: ' . (int) ($summary['itemsChecked'] ?? 0)
            . ' &middot; Total issues: ' . (int) ($summary['totalIssues'] ?? 0) . '</p>'
            . '<table><tr><th>Rule</th><th>Check</th><th>Issues</th></tr>' . $rows . '</table>';
    }

    /**
     * Flagged-items table: one row per item with its violated rule names.
     *
     * @param array<int, array<string, mixed>> $items
     * @param array<string, string>            $rules
     */
    private function renderItemsTable(array $items, array $rules): string
    {
        if ($items === []) {
            return '<h2>Flagged items</h2><p class="muted">No issues found.</p>';
        }

        $rows = '';
        foreach ($items as $item) {
            $rows .= $this->row([
                '<span class="id">' . $this->esc((string) ($item['id'] ?? '')) . '</span>',
                $this->esc((string) ($item['type'] ?? '')),
                $this->esc((string) ($item['status'] ?? '')),
                $this->esc((string) ($item['title'] ?? '')),
                $this->esc($this->issueNames($item['issues'] ?? [], $rules)),
            ]);
        }

        return '<h2>Flagged items (' . count($items) . ')</h2>'
            . '<table><tr><th>ID</th><th>Type</th><th>Status</th><th>Title</th><th>Issues</th></tr>'
            . $rows . '</table>';
    }

    /**
     * Map violated rule ids to their human-readable names, comma-joined.
     *
     * @param array<int, string>     $issues
     * @param array<string, string>  $rules
     */
    private function issueNames(array $issues, array $rules): string
    {
        $names = [];
        foreach ($issues as $ruleId) {
            $names[] = $rules[$ruleId] ?? $ruleId;
        }

        return implode(', ', $names);
    }

    /**
     * A table row from already-safe cell HTML.
     *
     * @param array<int, string> $cells
     */
    private function row(array $cells): string
    {
        return '<tr><td>' . implode('</td><td>', $cells) . '</td></tr>';
    }

    /** Escape a dynamic value for safe HTML output (UTF-8). */
    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}
