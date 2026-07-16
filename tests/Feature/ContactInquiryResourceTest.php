<?php

namespace Tests\Feature;

use App\Filament\Resources\ContactInquiries\ContactInquiryResource;
use App\Filament\Resources\ContactInquiries\Pages\ListContactInquiries;
use App\Models\ContactInquiry;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactInquiryResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_admin_can_see_inquiries_in_the_table(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $inquiries = ContactInquiry::factory()->count(3)->create();

        Livewire::test(ListContactInquiries::class)
            ->assertCanSeeTableRecords($inquiries);
    }

    public function test_resource_is_not_registered_in_sidebar_navigation(): void
    {
        $this->assertFalse(ContactInquiryResource::shouldRegisterNavigation());
    }

    public function test_topbar_badge_counts_only_new_inquiries(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        ContactInquiry::factory()->count(2)->create();
        ContactInquiry::factory()->inProgress()->create();
        ContactInquiry::factory()->handled()->create();

        $html = view('filament.topbar.contact-inquiries-link')->render();

        $this->assertStringContainsString('fi-badge', $html);
        $this->assertStringContainsString('>2<', preg_replace('/\s+/', '', $html));
    }

    public function test_topbar_badge_is_hidden_when_no_new_inquiries(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        ContactInquiry::factory()->handled()->create();
        ContactInquiry::factory()->inProgress()->create();

        $html = view('filament.topbar.contact-inquiries-link')->render();

        $this->assertStringContainsString('Zprávy z webu', $html);
        $this->assertStringNotContainsString('fi-badge', $html);
    }

    public function test_topbar_link_is_hidden_without_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $html = view('filament.topbar.contact-inquiries-link')->render();

        $this->assertStringNotContainsString('Zprávy z webu', $html);
    }
}
