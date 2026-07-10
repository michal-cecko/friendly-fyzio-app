<?php

namespace Tests\Feature\Cms;

use App\Enums\EmailTemplateKey;
use App\Filament\Clusters\Obsah\Resources\EmailTemplates\EmailTemplateResource;
use App\Filament\Clusters\Obsah\Resources\EmailTemplates\Pages\EditEmailTemplates;
use App\Filament\Clusters\Obsah\Resources\EmailTemplates\Pages\ListEmailTemplates;
use App\Mason\EmailBrickRegistry;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\EmailTemplateRenderer;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\SettingsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_every_email_brick_is_well_formed(): void
    {
        foreach (EmailBrickRegistry::flat() as $brick) {
            $this->assertNotEmpty($brick::getId(), "{$brick} has an empty id");
            $this->assertNotEmpty($brick::getLabel(), "{$brick} has an empty label");
            // Calling getIcon() validates the referenced Heroicon enum case exists.
            $brick::getIcon();
        }

        $this->addToAssertionCount(1);
    }

    public function test_seeder_creates_one_template_per_trigger(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        foreach (EmailTemplateKey::cases() as $key) {
            $template = EmailTemplate::forKey($key);

            $this->assertNotNull($template, "Missing template for {$key->value}");
            $this->assertNotEmpty($template->content);
        }
    }

    public function test_renderer_wraps_bricks_in_layout_and_substitutes_tokens(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->seed(EmailTemplateSeeder::class);

        $key = EmailTemplateKey::ReservationPending;
        $html = EmailTemplateRenderer::render(EmailTemplate::forKey($key), $key->sampleContext());

        // Fixed layout chrome: header logo + footer address/contact from settings.
        $this->assertStringContainsString('Friendly', $html);
        $this->assertStringContainsString('FriendlyFyzio s.r.o.', $html);
        $this->assertStringContainsString('info@friendlyfyzio.cz', $html);

        // Inline text token replaced (greeting brick).
        $this->assertStringContainsString('Děkujeme za vaši rezervaci, Jana,', $html);
        // Reservation-details brick token replaced with sample data.
        $this->assertStringContainsString('Klasická masáž (60 min)', $html);
        $this->assertStringContainsString('Mgr. Petra Nováková', $html);

        // No raw tokens survive rendering.
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_cancellation_template_renders_danger_detail_box_with_reason(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->seed(EmailTemplateSeeder::class);

        $key = EmailTemplateKey::ReservationCancelled;
        $html = EmailTemplateRenderer::render(EmailTemplate::forKey($key), $key->sampleContext());

        $this->assertStringContainsString('Zrušená návštěva', $html);
        $this->assertStringContainsString('Zrušeno klientem', $html);
        // Danger box title colour.
        $this->assertStringContainsString('#EF4444', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_lifecycle_emails_carry_a_single_manage_button(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->seed(EmailTemplateSeeder::class);

        $expected = [
            EmailTemplateKey::ReservationPending->value => 'Potvrdit rezervaci',
            EmailTemplateKey::ReservationConfirmed->value => 'Spravovat rezervaci',
            EmailTemplateKey::ReservationReminder->value => 'Spravovat rezervaci',
        ];

        foreach ($expected as $keyValue => $buttonLabel) {
            $key = EmailTemplateKey::from($keyValue);
            $html = EmailTemplateRenderer::render(EmailTemplate::forKey($key), $key->sampleContext());

            $this->assertStringContainsString($buttonLabel, $html, "{$key->value} is missing its manage button");
            // The confirm/cancel split collapsed into one link; no separate cancel button remains.
            $this->assertStringNotContainsString('Zrušit rezervaci', $html);
            $this->assertStringNotContainsString('{{', $html);
        }
    }

    public function test_reminder_template_is_a_pure_reminder_for_confirmed_visits(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->seed(EmailTemplateSeeder::class);

        $key = EmailTemplateKey::ReservationReminder;
        $html = EmailTemplateRenderer::render(EmailTemplate::forKey($key), $key->sampleContext());

        // Reminder goes to already-confirmed visits, so it must not nudge to confirm or warn of auto-cancel.
        $this->assertStringNotContainsString('Potvrdit účast', $html);
        $this->assertStringNotContainsString('automaticky zrušena', $html);
        $this->assertStringContainsString('Spravovat rezervaci', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_confirmed_template_reflects_customer_confirmation(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->seed(EmailTemplateSeeder::class);

        $key = EmailTemplateKey::ReservationConfirmed;
        $html = EmailTemplateRenderer::render(EmailTemplate::forKey($key), $key->sampleContext());

        $this->assertStringContainsString('Vaše rezervace je potvrzena', $html);
        // The customer confirms now — not the therapist.
        $this->assertStringNotContainsString('terapeutem', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_change_template_renders_both_original_and_new_boxes(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->seed(EmailTemplateSeeder::class);

        $key = EmailTemplateKey::ReservationChanged;
        $html = EmailTemplateRenderer::render(EmailTemplate::forKey($key), $key->sampleContext());

        $this->assertStringContainsString('Původní termín', $html);
        $this->assertStringContainsString('Nový termín', $html);
        // Original and new date/time both substituted from their own tokens.
        $this->assertStringContainsString('20. dubna 2026, 11:00', $html);
        $this->assertStringContainsString('22. dubna 2026, 15:00', $html);
        $this->assertStringContainsString('jana@example.cz', $html);
        // Success box border colour on the new-termín box.
        $this->assertStringContainsString('#22C55E', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_storno_payment_template_renders_the_payment_box(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->seed(EmailTemplateSeeder::class);

        $key = EmailTemplateKey::ReservationStornoPayment;
        $html = EmailTemplateRenderer::render(EmailTemplate::forKey($key), $key->sampleContext());

        $this->assertStringContainsString('Částka', $html);
        $this->assertStringContainsString('Variabilní symbol', $html);
        $this->assertStringContainsString('CZ65 0800 0000 1920 0014 5399', $html);
        $this->assertStringContainsString('600 Kč', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_every_seeded_template_renders_without_error(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->seed(EmailTemplateSeeder::class);

        foreach (EmailTemplateKey::cases() as $key) {
            $html = EmailTemplateRenderer::render(EmailTemplate::forKey($key), $key->sampleContext());

            $this->assertStringContainsString('<!DOCTYPE html>', $html);
            $this->assertStringNotContainsString('{{', $html);
        }
    }

    public function test_admin_sees_seeded_templates_and_cannot_create(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListEmailTemplates::class)
            ->assertCanSeeTableRecords(EmailTemplate::all());

        $this->assertFalse(EmailTemplateResource::canCreate());
    }

    public function test_admin_can_edit_subject_and_preview_action_exists(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $this->actingAs(User::factory()->admin()->create());

        $template = EmailTemplate::forKey(EmailTemplateKey::ReservationConfirmed);

        Livewire::test(EditEmailTemplates::class, ['record' => $template->getKey()])
            ->assertActionExists('preview')
            ->fillForm(['subject' => 'Upravený předmět'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('email_templates', [
            'id' => $template->getKey(),
            'subject' => 'Upravený předmět',
        ]);
    }
}
