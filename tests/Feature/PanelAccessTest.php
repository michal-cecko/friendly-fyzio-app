<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_access_admin_panel(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_access_admin_panel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertSuccessful();
    }

    public function test_therapist_can_access_admin_panel(): void
    {
        $therapist = User::factory()->therapist()->create();

        $this->actingAs($therapist)->get('/admin')->assertSuccessful();
    }

    public function test_customer_can_access_client_panel(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->get('/klientska-zona')->assertSuccessful();
    }

    public function test_admin_visiting_client_panel_is_redirected_to_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/klientska-zona')->assertRedirect('/admin');
    }

    public function test_therapist_visiting_client_panel_is_redirected_to_admin(): void
    {
        $therapist = User::factory()->therapist()->create();

        $this->actingAs($therapist)->get('/klientska-zona')->assertRedirect('/admin');
    }

    public function test_unauthenticated_admin_visit_redirects_to_single_client_login(): void
    {
        $this->get('/admin')->assertRedirect('/klientska-zona/login');
    }

    public function test_unauthenticated_client_visit_redirects_to_login(): void
    {
        $this->get('/klientska-zona')->assertRedirect('/klientska-zona/login');
    }
}
