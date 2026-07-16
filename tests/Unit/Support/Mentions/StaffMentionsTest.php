<?php

namespace Tests\Unit\Support\Mentions;

use App\Support\Mentions\StaffMentions;
use PHPUnit\Framework\TestCase;

class StaffMentionsTest extends TestCase
{
    private function mention(string $id, string $name): string
    {
        return '<span data-type="mention" data-id="'.$id.'" data-label="'.$name.'" data-char="@">@'.$name.'</span>';
    }

    public function test_extract_ids_finds_span_and_anchor_mentions_in_any_attribute_order(): void
    {
        $html = '<p>Viz '.$this->mention('id-a', 'Jana').' a '
            .'<a data-id="id-b" href="#" data-type="mention">@Petr</a> a znovu '
            .$this->mention('id-a', 'Jana').'</p>';

        $this->assertSame(['id-a', 'id-b'], StaffMentions::extractIds($html));
    }

    public function test_extract_ids_returns_nothing_for_plain_html_or_null(): void
    {
        $this->assertSame([], StaffMentions::extractIds('<p>Bez zmínky, e-mail: info@example.com</p>'));
        $this->assertSame([], StaffMentions::extractIds(null));
        $this->assertSame([], StaffMentions::extractIds('<span data-type="other" data-id="x">@Ne</span>'));
    }

    public function test_extract_labels_prefers_data_label_and_falls_back_to_inner_text(): void
    {
        $html = '<p>'.$this->mention('id-a', 'Jana Nováková')
            .' <span data-type="mention" data-id="id-b">@Petr Svoboda</span></p>';

        $this->assertSame([
            'id-a' => 'Jana Nováková',
            'id-b' => 'Petr Svoboda',
        ], StaffMentions::extractLabels($html));
    }

    public function test_excerpt_takes_fifteen_characters_around_the_mention(): void
    {
        $html = '<p>Tohle je delší text, který chci probrat s '
            .$this->mention('id-a', 'Jana Nováková')
            .' na příští poradě týmu.</p>';

        $this->assertSame(
            '…chci probrat s @Jana Nováková na příští pora…',
            StaffMentions::excerptAround($html, 'id-a'),
        );
    }

    public function test_excerpt_handles_mention_at_the_edges_without_ellipses(): void
    {
        $html = '<p>'.$this->mention('id-a', 'Jana').' prosím mrkni.</p>';

        $this->assertSame('@Jana prosím mrkni.', StaffMentions::excerptAround($html, 'id-a'));
        $this->assertSame('', StaffMentions::excerptAround('<p>nic</p>', 'id-a'));
    }

    public function test_excerpt_counts_multibyte_czech_characters(): void
    {
        // Before-text is 16 characters ("aaaaaaaaaa" + "ěščřž" + space): exactly
        // one character must be cut, which only works with multibyte counting.
        $html = '<p>aaaaaaaaaaěščřž '.$this->mention('id-a', 'Jana').' ok.</p>';

        $this->assertSame(
            '…aaaaaaaaaěščřž @Jana ok.',
            StaffMentions::excerptAround($html, 'id-a'),
        );
    }

    public function test_replace_mentions_swaps_elements_and_leaves_other_html_alone(): void
    {
        $html = '<p>Tým: '.$this->mention('id-a', 'Jana').' a <strong>text</strong></p>';

        $replaced = StaffMentions::replaceMentions(
            $html,
            fn (?string $id, string $label): string => '<a href="/profil">'.$label.'</a>',
        );

        $this->assertSame('<p>Tým: <a href="/profil">Jana</a> a <strong>text</strong></p>', $replaced);
    }
}
