<?php

namespace Tests\Feature;

use App\Enums\SettingValueType;
use App\Livewire\ContactForm;
use App\Models\ContactInquiry;
use App\Models\Setting;
use App\Notifications\ContactInquiryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::updateOrCreate(
            ['key' => 'web.contact_email'],
            ['value' => 'clinic@example.com', 'type' => SettingValueType::Text, 'label' => 'Kontaktní e-mail', 'group' => 'Web'],
        );
    }

    public function test_a_valid_submission_is_stored_and_emailed(): void
    {
        Notification::fake();

        Livewire::test(ContactForm::class)
            ->set('name', 'Jana Nováková')
            ->set('email', 'jana@example.com')
            ->set('phone', '+420 604 123 456')
            ->set('message', 'Dobrý den, ráda bych se objednala na vstupní vyšetření.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('status', 'sent')
            ->assertSet('name', '')
            ->assertSet('message', '');

        $this->assertDatabaseHas(ContactInquiry::class, [
            'name' => 'Jana Nováková',
            'email' => 'jana@example.com',
            'phone' => '+420 604 123 456',
        ]);

        Notification::assertSentOnDemand(
            ContactInquiryNotification::class,
            fn (ContactInquiryNotification $notification, array $channels, object $notifiable) => $notifiable->routes['mail'] === 'clinic@example.com'
        );
    }

    public function test_the_message_is_saved_even_when_no_recipient_is_configured(): void
    {
        Notification::fake();
        Setting::where('key', 'web.contact_email')->update(['value' => '']);

        Livewire::test(ContactForm::class)
            ->set('name', 'Petr Svoboda')
            ->set('email', 'petr@example.com')
            ->set('message', 'Mám dotaz ohledně laserové terapie prosím.')
            ->call('submit')
            ->assertSet('status', 'sent');

        $this->assertDatabaseHas(ContactInquiry::class, ['email' => 'petr@example.com']);
        Notification::assertNothingSent();
    }

    public function test_required_fields_are_validated_and_nothing_is_stored(): void
    {
        Notification::fake();

        Livewire::test(ContactForm::class)
            ->set('name', '')
            ->set('email', 'not-an-email')
            ->set('message', 'krátká')
            ->call('submit')
            ->assertHasErrors(['name' => 'required', 'email' => 'email', 'message' => 'min'])
            ->assertSet('status', null);

        $this->assertDatabaseCount(ContactInquiry::class, 0);
        Notification::assertNothingSent();
    }
}
