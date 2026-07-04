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

    public function test_navigation_badge_counts_only_new_inquiries(): void
    {
        ContactInquiry::factory()->count(2)->create();
        ContactInquiry::factory()->inProgress()->create();
        ContactInquiry::factory()->handled()->create();

        $this->assertSame('2', ContactInquiryResource::getNavigationBadge());
    }

    public function test_navigation_badge_is_hidden_when_no_new_inquiries(): void
    {
        ContactInquiry::factory()->handled()->create();
        ContactInquiry::factory()->inProgress()->create();

        $this->assertNull(ContactInquiryResource::getNavigationBadge());
    }
}
