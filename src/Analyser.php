<?php

declare(strict_types=1);

namespace App;

/**
 * Data processing/analysis.
 *
 * Runs the audit rules over each fetched item and collects the issues found.
 */
final class Analyser
{
    public function __construct(
        private readonly Logger $logger,
    ) {
    }

    /**
     * Run every rule over every item and return the structured analysis.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    public function analyse(array $items): array
    {
        // TODO: run each rule over each item; collect per-item and per-rule issues.
    }

    /**
     * R1 — Missing title.
     *
     * @param array<string, mixed> $item
     * @return bool True if the item violates the rule.
     */
    public function checkMissingTitle(array $item): bool
    {
        // TODO
    }

    /**
     * R2 — Short content.
     *
     * @param array<string, mixed> $item
     * @return bool True if the item violates the rule.
     */
    public function checkShortContent(array $item): bool
    {
        // TODO
    }

    /**
     * R3 — Missing featured image.
     *
     * @param array<string, mixed> $item
     * @return bool True if the item violates the rule.
     */
    public function checkMissingFeaturedImage(array $item): bool
    {
        // TODO
    }

    /**
     * R4 — Missing excerpt.
     *
     * @param array<string, mixed> $item
     * @return bool True if the item violates the rule.
     */
    public function checkMissingExcerpt(array $item): bool
    {
        // TODO
    }

    /**
     * R5 — Stale draft.
     *
     * @param array<string, mixed> $item
     * @return bool True if the item violates the rule.
     */
    public function checkStaleDraft(array $item): bool
    {
        // TODO
    }
}
