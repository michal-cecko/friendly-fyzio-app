<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAuthFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_staff_login_page_renders_branded_split_layout_with_passkey_option(): void
    {
        $this->get('/admin/login')
            ->assertSuccessful()
            ->assertSee('ff-logo-bright.svg', escape: false)
            ->assertSee('ff-auth-photo', escape: false)
            ->assertSee('passkeys/authentication-options', escape: false)
            ->assertSee('Přihlásit se přístupovým klíčem');
    }

    public function test_public_registration_page_renders_with_czech_strings(): void
    {
        $this->get(route('public.register'))
            ->assertSuccessful()
            ->assertSee('Vytvořit účet')
            ->assertSee('Jméno')
            ->assertSee('Příjmení');
    }

    public function test_admin_can_view_dashboard_with_brand_logo(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('ff-logo-bright.svg', escape: false);
    }

    public function test_admin_can_view_profile_page_with_czech_passkey_strings(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/profile')
            ->assertSuccessful()
            ->assertSee('Přístupové klíče');
    }
}
