<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_customer_is_bounced_to_the_verification_notice(): void
    {
        $customer = User::factory()->customer()->unverified()->create();

        $this->actingAs($customer)
            ->get('/muj-ucet')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_signed_verification_link_marks_the_email_verified(): void
    {
        $customer = User::factory()->customer()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $customer->getKey(),
            'hash' => sha1($customer->getEmailForVerification()),
        ]);

        $this->actingAs($customer)->get($url)->assertRedirect('/muj-ucet');

        $this->assertTrue($customer->fresh()->hasVerifiedEmail());
    }

    public function test_a_tampered_hash_is_rejected(): void
    {
        $customer = User::factory()->customer()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $customer->getKey(),
            'hash' => sha1('someone.else@example.cz'),
        ]);

        $this->actingAs($customer)->get($url)->assertForbidden();

        $this->assertFalse($customer->fresh()->hasVerifiedEmail());
    }

    public function test_an_unsigned_link_is_rejected(): void
    {
        $customer = User::factory()->customer()->unverified()->create();

        $this->actingAs($customer)
            ->get('/overeni-emailu/'.$customer->getKey().'/'.sha1($customer->getEmailForVerification()))
            ->assertForbidden();
    }

    public function test_verified_customer_reaches_the_client_zone(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->get('/muj-ucet')
            ->assertSuccessful();
    }
}
