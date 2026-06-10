<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('client');
    }

    public function test_unverified_customer_is_bounced_to_the_verification_prompt(): void
    {
        $customer = User::factory()->customer()->unverified()->create();

        $this->actingAs($customer)
            ->get('/klientska-zona')
            ->assertRedirect('/klientska-zona/email-verification/prompt');
    }

    public function test_signed_verification_link_marks_the_email_verified(): void
    {
        $customer = User::factory()->customer()->unverified()->create();

        $url = Filament::getVerifyEmailUrl($customer);

        $this->actingAs($customer)->get($url);

        $this->assertTrue($customer->fresh()->hasVerifiedEmail());
    }

    public function test_verified_customer_reaches_the_client_dashboard(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->get('/klientska-zona')
            ->assertSuccessful();
    }
}
