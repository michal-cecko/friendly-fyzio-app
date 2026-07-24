<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use STS\FilamentImpersonate\Facades\Impersonation;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_header_offers_a_leave_impersonation_link_while_impersonating(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($admin);
        Impersonation::enter($admin, $customer);

        $this->assertTrue(Impersonation::isImpersonating());

        $html = Blade::render('<x-site.header />');

        $this->assertStringContainsString('Ukončit impersonaci', $html);
        $this->assertStringContainsString(route('filament-impersonate.leave'), $html);
    }

    public function test_public_header_has_no_leave_impersonation_link_for_a_normal_session(): void
    {
        $this->actingAs(User::factory()->customer()->create());

        $html = Blade::render('<x-site.header />');

        $this->assertStringNotContainsString('Ukončit impersonaci', $html);
    }
}
