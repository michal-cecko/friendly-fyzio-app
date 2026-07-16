<?php

namespace Tests\Unit\Support;

use App\Support\RichText;
use PHPUnit\Framework\TestCase;

class RichTextTest extends TestCase
{
    public function test_from_plain_text_wraps_paragraphs_and_line_breaks(): void
    {
        $text = "První odstavec\ns druhým řádkem\n\nDruhý odstavec";

        $this->assertSame(
            "<p>První odstavec<br>\ns druhým řádkem</p><p>Druhý odstavec</p>",
            RichText::fromPlainText($text),
        );
    }

    public function test_from_plain_text_escapes_html_and_handles_blank_input(): void
    {
        $this->assertSame('<p>1 &lt; 2 &amp; spol.</p>', RichText::fromPlainText('1 < 2 & spol.'));
        $this->assertNull(RichText::fromPlainText(null));
        $this->assertNull(RichText::fromPlainText("  \n  "));
    }

    public function test_inline_unwraps_a_single_paragraph(): void
    {
        $this->assertSame('Ahoj <strong>světe</strong>', RichText::inline('<p>Ahoj <strong>světe</strong></p>'));
        $this->assertSame('<p>a</p><p>b</p>', RichText::inline('<p>a</p><p>b</p>'));
    }
}
