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

    public function test_unauthenticated_admin_visit_redirects_to_the_staff_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_the_staff_login_page_renders(): void
    {
        $this->get('/admin/login')->assertSuccessful();
    }

    public function test_the_retired_client_panel_redirects_to_the_public_zone(): void
    {
        $this->get('/klientska-zona')->assertRedirect('/muj-ucet');
        $this->get('/klientska-zona/login')->assertRedirect('/muj-ucet');
        $this->get('/klientska-zona/anything/deeper')->assertRedirect('/muj-ucet');
    }
}
