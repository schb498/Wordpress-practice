<?php

declare(strict_types=1);

namespace App\Tests;

use App\Analyser;
use App\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Each rule test builds a "clean" item that the rule should ignore and a
 * "violating" item that it should flag, so we cover both the positive and
 * the negative case and guard against a rule that always fires.
 */
#[CoversClass(Analyser::class)]
final class AnalyserTest extends TestCase
{
    private Analyser $analyser;

    protected function setUp(): void
    {
        // The logger only writes to STDERR; a real one is fine and keeps the
        // test focused on the rules rather than on a mock's expectations.
        $this->analyser = new Analyser(new Logger());
    }

    /** R1 — Missing title: flagged only when the rendered title is blank. */
    #[Test]
    public function r1_flags_a_blank_title_but_not_a_present_one(): void
    {
        $withTitle = $this->post(['title' => $this->rendered('Hello world')]);
        $blank     = $this->post(['title' => $this->rendered('   ')]);

        $this->assertFalse($this->analyser->checkMissingTitle($withTitle));
        $this->assertTrue($this->analyser->checkMissingTitle($blank));
    }

    /**
     * R1 edge — a missing `title` key (or one with no `rendered`) reads as an
     * empty string, so it is flagged rather than throwing.
     */
    #[Test]
    public function r1_flags_a_title_field_that_is_absent_entirely(): void
    {
        $this->assertTrue($this->analyser->checkMissingTitle([]));
        $this->assertTrue($this->analyser->checkMissingTitle(['title' => []]));
    }

    /**
     * R1 edge — the title is trimmed but NOT HTML-decoded, so an entity-only
     * title like `&nbsp;` counts as present. This pins current behaviour; if
     * R1 ever switches to plain-text comparison, update this expectation.
     */
    #[Test]
    public function r1_treats_an_entity_only_title_as_present(): void
    {
        $item = $this->post(['title' => $this->rendered('&nbsp;')]);

        $this->assertFalse($this->analyser->checkMissingTitle($item));
    }

    /** R2 — Short content: flagged when the visible text is under 150 words. */
    #[Test]
    public function r2_flags_content_under_150_words(): void
    {
        $long  = $this->post(['content' => $this->rendered($this->words(150))]);
        $short = $this->post(['content' => $this->rendered($this->words(149))]);

        $this->assertFalse($this->analyser->checkShortContent($long));
        $this->assertTrue($this->analyser->checkShortContent($short));
    }

    /** R2 counts visible words only — HTML tags do not inflate the count. */
    #[Test]
    public function r2_counts_visible_words_after_stripping_html(): void
    {
        // 140 words behind a wall of markup: the tags contribute no words, so
        // this is still short. (If tags were counted it would sail past 150.)
        $html = '<div><p><strong>' . $this->words(140) . '</strong></p></div>';
        $item = $this->post(['content' => $this->rendered($html)]);

        $this->assertTrue($this->analyser->checkShortContent($item));
    }

    /**
     * R2 edge — empty content, and content that is *only* markup (zero visible
     * words), both count as 0 words and are flagged.
     */
    #[Test]
    public function r2_flags_empty_and_markup_only_content(): void
    {
        $empty     = $this->post(['content' => $this->rendered('')]);
        $tagsOnly  = $this->post(['content' => $this->rendered('<br><hr><img src="x.jpg">')]);
        $absentKey = $this->post();
        unset($absentKey['content']);

        $this->assertTrue($this->analyser->checkShortContent($empty));
        $this->assertTrue($this->analyser->checkShortContent($tagsOnly));
        $this->assertTrue($this->analyser->checkShortContent($absentKey));
    }

    /** R3 — Missing featured image: posts with featured_media 0, pages exempt. */
    #[Test]
    public function r3_flags_a_post_without_a_featured_image_but_never_a_page(): void
    {
        $postNoImage = $this->post(['featured_media' => 0]);
        $postWith    = $this->post(['featured_media' => 42]);
        $pageNoImage = $this->page(['featured_media' => 0]);

        $this->assertTrue($this->analyser->checkMissingFeaturedImage($postNoImage));
        $this->assertFalse($this->analyser->checkMissingFeaturedImage($postWith));
        $this->assertFalse($this->analyser->checkMissingFeaturedImage($pageNoImage));
    }

    /**
     * R3 edge — a post with no `featured_media` key defaults to 0 (flagged),
     * and the rule ignores any non-post type, not just pages.
     */
    #[Test]
    public function r3_treats_an_absent_media_key_as_missing_and_only_checks_posts(): void
    {
        $postNoKey = $this->post();
        unset($postNoKey['featured_media']);
        $attachment = $this->post(['type' => 'attachment', 'featured_media' => 0]);

        $this->assertTrue($this->analyser->checkMissingFeaturedImage($postNoKey));
        $this->assertFalse($this->analyser->checkMissingFeaturedImage($attachment));
    }

    /** R4 — Missing excerpt: published posts only; drafts and pages exempt. */
    #[Test]
    public function r4_flags_a_published_post_with_an_empty_excerpt(): void
    {
        $publishedNoExcerpt = $this->post([
            'status'  => 'publish',
            'excerpt' => $this->rendered(''),
        ]);
        $publishedWithExcerpt = $this->post([
            'status'  => 'publish',
            'excerpt' => $this->rendered('A short summary.'),
        ]);
        $draftNoExcerpt = $this->post([
            'status'  => 'draft',
            'excerpt' => $this->rendered(''),
        ]);

        $this->assertTrue($this->analyser->checkMissingExcerpt($publishedNoExcerpt));
        $this->assertFalse($this->analyser->checkMissingExcerpt($publishedWithExcerpt));
        $this->assertFalse($this->analyser->checkMissingExcerpt($draftNoExcerpt));
    }

    /**
     * R4 edge — an excerpt that is only markup/whitespace has no visible text,
     * so a published post is flagged; a published *page* never is.
     */
    #[Test]
    public function r4_flags_a_whitespace_only_excerpt_but_never_a_page(): void
    {
        $postBlankHtml = $this->post([
            'status'  => 'publish',
            'excerpt' => $this->rendered('<p> </p>'),
        ]);
        $pageNoExcerpt = $this->page([
            'status'  => 'publish',
            'excerpt' => $this->rendered(''),
        ]);

        $this->assertTrue($this->analyser->checkMissingExcerpt($postBlankHtml));
        $this->assertFalse($this->analyser->checkMissingExcerpt($pageNoExcerpt));
    }

    /** R5 — Stale draft: a draft not modified for more than 30 days. */
    #[Test]
    public function r5_flags_a_draft_older_than_30_days_but_not_a_fresh_one(): void
    {
        $stale = $this->post([
            'status'   => 'draft',
            'modified' => $this->daysAgo(31),
        ]);
        $fresh = $this->post([
            'status'   => 'draft',
            'modified' => $this->daysAgo(5),
        ]);
        $oldButPublished = $this->post([
            'status'   => 'publish',
            'modified' => $this->daysAgo(365),
        ]);

        $this->assertTrue($this->analyser->checkStaleDraft($stale));
        $this->assertFalse($this->analyser->checkStaleDraft($fresh));
        $this->assertFalse($this->analyser->checkStaleDraft($oldButPublished));
    }

    /**
     * R5 boundary — the rule is "more than 30 days", so a draft modified
     * exactly 30 days ago is NOT stale; one day older is.
     */
    #[Test]
    public function r5_uses_a_strict_greater_than_30_day_boundary(): void
    {
        $exactly30 = $this->post(['status' => 'draft', 'modified' => $this->daysAgo(30)]);
        $justOver  = $this->post(['status' => 'draft', 'modified' => $this->daysAgo(31)]);

        $this->assertFalse($this->analyser->checkStaleDraft($exactly30));
        $this->assertTrue($this->analyser->checkStaleDraft($justOver));
    }

    /**
     * R5 edge — a draft whose `modified` date is missing or unparseable is
     * treated as maximally old, so it is flagged rather than skipped.
     */
    #[Test]
    public function r5_flags_a_draft_with_a_missing_or_unparseable_date(): void
    {
        $noDate      = $this->post(['status' => 'draft']);
        unset($noDate['modified']);
        $garbageDate = $this->post(['status' => 'draft', 'modified' => 'not-a-date']);

        $this->assertTrue($this->analyser->checkStaleDraft($noDate));
        $this->assertTrue($this->analyser->checkStaleDraft($garbageDate));
    }

    /**
     * analyse() aggregates per-rule counts and flags only items with issues.
     *
     * A clean published post (long content, title, image, excerpt) sits next
     * to a post that trips R1, R2, R3 and R4 at once. The clean item must not
     * appear in the flagged list, and each rule's counter reflects the hits.
     */
    #[Test]
    public function analyse_counts_issues_per_rule_and_flags_only_bad_items(): void
    {
        $clean = $this->post([
            'id'             => 1,
            'status'         => 'publish',
            'title'          => $this->rendered('A good post'),
            'content'        => $this->rendered($this->words(200)),
            'excerpt'        => $this->rendered('A summary.'),
            'featured_media' => 7,
        ]);
        $bad = $this->post([
            'id'             => 2,
            'status'         => 'publish',
            'title'          => $this->rendered(''),          // R1
            'content'        => $this->rendered($this->words(3)), // R2
            'excerpt'        => $this->rendered(''),          // R4
            'featured_media' => 0,                             // R3
        ]);

        $result = $this->analyser->analyse([$clean, $bad]);

        $this->assertSame(2, $result['itemsChecked']);
        $this->assertSame(
            ['R1' => 1, 'R2' => 1, 'R3' => 1, 'R4' => 1, 'R5' => 0],
            $result['issuesByRule'],
        );

        // Only the bad item is flagged, with exactly its four rules.
        $this->assertCount(1, $result['items']);
        $flagged = $result['items'][0];
        $this->assertSame(2, $flagged['id']);
        $this->assertSame('(untitled)', $flagged['title']);
        $this->assertEqualsCanonicalizing(['R1', 'R2', 'R3', 'R4'], $flagged['issues']);
    }

    // --- item builders -----------------------------------------------------

    /**
     * A well-formed post that violates no rule, with fields overridable.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function post(array $overrides = []): array
    {
        return array_merge([
            'id'             => 100,
            'type'           => 'post',
            'status'         => 'publish',
            'title'          => $this->rendered('Title'),
            'content'        => $this->rendered($this->words(200)),
            'excerpt'        => $this->rendered('An excerpt.'),
            'modified'       => $this->daysAgo(1),
            'featured_media' => 1,
        ], $overrides);
    }

    /**
     * A page (type=page); pages are exempt from the post-only rules R3/R4.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function page(array $overrides = []): array
    {
        return $this->post(array_merge(['type' => 'page'], $overrides));
    }

    /**
     * A WordPress `{ rendered: ... }` field wrapper.
     *
     * @return array{rendered: string}
     */
    private function rendered(string $html): array
    {
        return ['rendered' => $html];
    }

    /** A string of $n space-separated words ("w1 w2 …"). */
    private function words(int $n): string
    {
        return implode(' ', array_map(static fn (int $i): string => "w{$i}", range(1, $n)));
    }

    /** An ISO 8601 timestamp $days in the past (WordPress `modified` shape). */
    private function daysAgo(int $days): string
    {
        return (new \DateTimeImmutable("-{$days} days"))->format('Y-m-d\TH:i:s');
    }
}
