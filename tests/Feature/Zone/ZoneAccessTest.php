<?php

namespace Tests\Feature\Zone;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ZoneAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function zonePages(): array
    {
        return [
            'dashboard' => ['/muj-ucet'],
            'reservations' => ['/muj-ucet/rezervace'],
            'courses' => ['/muj-ucet/kurzy'],
            'tokens' => ['/muj-ucet/nahrady'],
            'credits' => ['/muj-ucet/kredity'],
            'payments' => ['/muj-ucet/platby'],
            'invoices' => ['/muj-ucet/faktury'],
            'profile' => ['/muj-ucet/profil'],
        ];
    }

    #[DataProvider('zonePages')]
    public function test_every_zone_page_renders_for_a_verified_customer(string $path): void
    {
        $customer = User::factory()->customer()->create(['email_verified_at' => now()]);

        $this->actingAs($customer)->get($path)->assertOk();
    }

    public function test_guests_are_sent_to_the_login_with_a_return_path(): void
    {
        $this->get('/muj-ucet/rezervace')
            ->assertRedirect(route('public.login', ['return' => '/muj-ucet/rezervace']));
    }

    public function test_unverified_customers_must_verify_first(): void
    {
        $customer = User::factory()->customer()->create(['email_verified_at' => null]);

        $this->actingAs($customer)->get('/muj-ucet')->assertRedirect(route('verification.notice'));
    }

    public function test_staff_are_redirected_to_the_admin_panel(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->therapist()->create()] as $staff) {
            $this->actingAs($staff)->get('/muj-ucet')->assertRedirect('/admin');
        }
    }

    public function test_deactivated_customers_are_signed_out(): void
    {
        $customer = User::factory()->customer()->create([
            'email_verified_at' => now(),
            'deactivated_at' => now(),
        ]);

        $this->actingAs($customer)->get('/muj-ucet')->assertRedirect(route('public.login'));

        $this->assertGuest();
    }
}
