<?php

namespace Tests\Feature\Emails;

use App\Enums\EmailTemplateKey;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationTemplateNotification;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The phone rendering of every email hangs on one `@media` block in the layout:
 * the bricks carry inline (desktop) styles, and only a matching `.e-*` rule
 * overrides them on a narrow viewport.
 */
class EmailMobileStylesTest extends TestCase
{
    use RefreshDatabase;

    protected function layout(): string
    {
        return File::get(resource_path('views/emails/layout.blade.php'));
    }

    protected function mobileBlock(): string
    {
        preg_match('/@media only screen and \(max-width: 620px\) \{(.*?)\n        \}/s', $this->layout(), $matches);

        $this->assertNotEmpty($matches, 'The layout no longer has a mobile media query.');

        return $matches[1];
    }

    public function test_every_style_hook_used_in_an_email_is_sized_for_mobile(): void
    {
        $block = $this->mobileBlock();

        $used = [];

        foreach (File::allFiles(resource_path('views/emails')) as $file) {
            preg_match_all('/class="(e-[a-z- ]*)"/', $file->getContents(), $matches);

            foreach ($matches[1] as $classAttribute) {
                foreach (preg_split('/\s+/', trim($classAttribute)) as $class) {
                    $used[$class] = true;
                }
            }
        }

        $this->assertNotEmpty($used);

        foreach (array_keys($used) as $class) {
            $this->assertStringContainsString(
                ".{$class}",
                $block,
                "The mobile styles do not cover .{$class}, so it keeps its desktop size on phones.",
            );
        }
    }

    public function test_mobile_overrides_beat_the_inline_styles_they_replace(): void
    {
        // Without !important the bricks' inline styles win and the block is inert.
        $declarations = array_filter(array_map(
            'trim',
            preg_split('/[{}]/', $this->mobileBlock()) ?: [],
        ));

        foreach ($declarations as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            $this->assertStringContainsString('!important', $declaration);
        }
    }

    public function test_a_rendered_email_carries_the_mobile_styles(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $client = User::factory()->customer()->create();
        $reservation = Reservation::factory()->create(['client_id' => $client->getKey()]);

        $html = (new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationCreated))
            ->toMail($client)->viewData['html'] ?? '';

        $this->assertStringContainsString('@media only screen and (max-width: 620px)', $html);
        // Body copy is the size the whole scale is judged by — 16px after the 10%
        // trim, matching the phone's own default rather than towering over it.
        $this->assertStringContainsString('.e-content p, .e-content li { font-size: 16px !important; }', $html);
    }
}
