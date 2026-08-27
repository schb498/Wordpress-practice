<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;

/**
 * Stage 3: Data processing / analysis.
 *
 * Runs the five audit rules over each fetched item and collects the issues,
 * both as per-rule counts and as a per-item "what to fix" list.
 */
final class Analyser
{
    private const MIN_WORDS = 150;
    private const STALE_DRAFT_DAYS = 30;

    /** Rule id => human-readable name (report headings, stable order). */
    private const RULE_NAMES = [
        'R1' => 'Missing title',
        'R2' => 'Short content',
        'R3' => 'Missing featured image',
        'R4' => 'Missing excerpt',
        'R5' => 'Stale draft',
    ];

    public function __construct(
        private readonly Logger $logger,
    ) {
    }

    /**
     * Run every applicable rule over every item.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    public function analyse(array $items): array
    {
        $issuesByRule = array_fill_keys(array_keys(self::RULE_NAMES), 0);
        $flagged = [];

        foreach ($items as $item) {
            $issues = $this->rulesViolatedBy($item);
            if ($issues === []) {
                continue;
            }
            foreach ($issues as $ruleId) {
                $issuesByRule[$ruleId]++;
            }
            $flagged[] = $this->flaggedItem($item, $issues);
        }

        $this->logger->info(sprintf(
            'Analysed %d items; %d have issues',
            count($items),
            count($flagged),
        ));

        return [
            'itemsChecked' => count($items),
            'issuesByRule' => $issuesByRule,
            'rules'        => self::RULE_NAMES,
            'items'        => $flagged,
        ];
    }

    /**
     * The rule ids an item violates (applicable rules only).
     *
     * @param array<string, mixed> $item
     * @return array<int, string>
     */
    private function rulesViolatedBy(array $item): array
    {
        $violated = [];
        foreach ($this->rules() as $ruleId => $check) {
            if ($check($item)) {
                $violated[] = $ruleId;
            }
        }

        return $violated;
    }

    /**
     * Rule id => predicate. A predicate returns true only for a genuine
     * violation; a rule that does not apply to an item returns false.
     *
     * @return array<string, callable(array<string, mixed>): bool>
     */
    private function rules(): array
    {
        return [
            'R1' => fn (array $i): bool => $this->checkMissingTitle($i),
            'R2' => fn (array $i): bool => $this->checkShortContent($i),
            'R3' => fn (array $i): bool => $this->checkMissingFeaturedImage($i),
            'R4' => fn (array $i): bool => $this->checkMissingExcerpt($i),
            'R5' => fn (array $i): bool => $this->checkStaleDraft($i),
        ];
    }

    /**
     * A flagged item's report entry.
     *
     * @param array<string, mixed> $item
     * @param array<int, string>   $issues
     * @return array<string, mixed>
     */
    private function flaggedItem(array $item, array $issues): array
    {
        $title = $this->plainText($this->rendered($item, 'title'));

        return [
            'id'     => $item['id'] ?? null,
            'type'   => $item['type'] ?? null,
            'status' => $item['status'] ?? null,
            'title'  => $title === '' ? '(untitled)' : $title,
            'issues' => $issues,
        ];
    }

    /**
     * R1 — Missing title.
     *
     * @param array<string, mixed> $item
     */
    public function checkMissingTitle(array $item): bool
    {
        return trim($this->rendered($item, 'title')) === '';
    }

    /**
     * R2 — Short content.
     *
     * @param array<string, mixed> $item
     */
    public function checkShortContent(array $item): bool
    {
        $text = $this->plainText($this->rendered($item, 'content'));

        return $this->wordCount($text) < self::MIN_WORDS;
    }

    /**
     * R3 — Missing featured image (posts only).
     *
     * @param array<string, mixed> $item
     */
    public function checkMissingFeaturedImage(array $item): bool
    {
        if (!$this->isPost($item)) {
            return false;
        }

        return (int) ($item['featured_media'] ?? 0) === 0;
    }

    /**
     * R4 — Missing excerpt (published posts only).
     *
     * @param array<string, mixed> $item
     */
    public function checkMissingExcerpt(array $item): bool
    {
        if (!$this->isPost($item) || ($item['status'] ?? null) !== 'publish') {
            return false;
        }

        return $this->plainText($this->rendered($item, 'excerpt')) === '';
    }

    /**
     * R5 — Stale draft (a draft not modified for over 30 days).
     *
     * @param array<string, mixed> $item
     */
    public function checkStaleDraft(array $item): bool
    {
        if (($item['status'] ?? null) !== 'draft') {
            return false;
        }

        return $this->daysSince((string) ($item['modified'] ?? '')) > self::STALE_DRAFT_DAYS;
    }

    // --- helpers -----------------------------------------------------------

    /**
     * @param array<string, mixed> $item
     */
    private function isPost(array $item): bool
    {
        return ($item['type'] ?? null) === 'post';
    }

    /**
     * Safely read a WordPress rendered field, e.g. $item['title']['rendered'].
     *
     * @param array<string, mixed> $item
     */
    private function rendered(array $item, string $field): string
    {
        $value = $item[$field] ?? null;

        return is_array($value) ? (string) ($value['rendered'] ?? '') : '';
    }

    /** Strip HTML to visible text: no tags, decoded entities, single spaces. */
    private function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function wordCount(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $text) ?: []);
    }

    /** Whole days between an ISO 8601 timestamp and now; PHP_INT_MAX if unparseable. */
    private function daysSince(string $timestamp): int
    {
        if ($timestamp === '') {
            return PHP_INT_MAX;
        }

        $modified = date_create_immutable($timestamp);
        if ($modified === false) {
            return PHP_INT_MAX;
        }

        return (new DateTimeImmutable())->diff($modified)->days;
    }
}
