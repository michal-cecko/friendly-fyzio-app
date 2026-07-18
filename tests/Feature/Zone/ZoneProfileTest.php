<?php

namespace Tests\Feature\Zone;

use App\Livewire\Zone\Profile;
use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ZoneProfileTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        $customer = User::factory()->customer()->create([
            'email_verified_at' => now(),
            'password' => Hash::make('stareheslo'),
        ]);

        $customer->clientProfile()->create();

        return $customer;
    }

    public function test_personal_details_save(): void
    {
        $customer = $this->customer();

        Livewire::actingAs($customer)
            ->test(Profile::class)
            ->set('name', 'Jana Nová')
            ->set('phone', '+420 777 000 111')
            ->call('saveDetails')
            ->assertHasNoErrors();

        $customer->refresh();

        $this->assertSame('Jana Nová', $customer->name);
        $this->assertSame('+420 777 000 111', $customer->phone);
        // Untouched e-mail keeps its verification.
        $this->assertNotNull($customer->email_verified_at);
    }

    public function test_changing_the_email_requires_re_verification(): void
    {
        Notification::fake();

        $customer = $this->customer();

        Livewire::actingAs($customer)
            ->test(Profile::class)
            ->set('email', 'nova.adresa@example.cz')
            ->call('saveDetails')
            ->assertHasNoErrors()
            ->assertRedirect(route('verification.notice'));

        $customer->refresh();

        $this->assertSame('nova.adresa@example.cz', $customer->email);
        $this->assertNull($customer->email_verified_at);

        Notification::assertSentTo($customer, VerifyEmailNotification::class);
    }

    public function test_an_email_already_in_use_is_rejected(): void
    {
        $customer = $this->customer();
        $other = User::factory()->customer()->create();

        Livewire::actingAs($customer)
            ->test(Profile::class)
            ->set('email', $other->email)
            ->call('saveDetails')
            ->assertHasErrors('email');
    }

    public function test_the_password_changes_only_with_the_current_one(): void
    {
        $customer = $this->customer();

        Livewire::actingAs($customer)
            ->test(Profile::class)
            ->set('current_password', 'spatneheslo')
            ->set('password', 'noveheslo123')
            ->set('password_confirmation', 'noveheslo123')
            ->call('savePassword')
            ->assertHasErrors('current_password');

        Livewire::actingAs($customer)
            ->test(Profile::class)
            ->set('current_password', 'stareheslo')
            ->set('password', 'noveheslo123')
            ->set('password_confirmation', 'noveheslo123')
            ->call('savePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('noveheslo123', $customer->fresh()->password));
    }

    public function test_company_billing_details_persist_to_the_client_profile(): void
    {
        $customer = $this->customer();

        Livewire::actingAs($customer)
            ->test(Profile::class)
            ->set('billing_name', 'Firma s.r.o.')
            ->set('company_ico', '12345678')
            ->set('company_dic', 'CZ12345678')
            ->set('billing_address', 'Ulice 1, Ostrava')
            ->call('saveBilling')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('client_profiles', [
            'user_id' => $customer->id,
            'billing_name' => 'Firma s.r.o.',
            'company_ico' => '12345678',
            'company_dic' => 'CZ12345678',
            'billing_address' => 'Ulice 1, Ostrava',
        ]);
    }
}
