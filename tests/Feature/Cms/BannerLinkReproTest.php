<?php

namespace Tests\Feature\Cms;

use App\Enums\BannerType;
use App\Filament\Clusters\Obsah\Resources\Banners\Pages\EditBanner;
use App\Models\Banner;
use App\Models\StaffProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BannerLinkReproTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_banner_cta_hydrates_kind_from_stored_ref(): void
    {
        $therapist = StaffProfile::factory()->published()->create();

        $banner = Banner::factory()->create([
            'type' => BannerType::Floating,
            'content' => [
                'title' => 'Test',
                'cta_link_type' => 'internal',
                'cta_link_ref' => "therapist:{$therapist->id}",
            ],
        ]);

        $content = Livewire::test(EditBanner::class, ['record' => $banner->getKey()])
            ->get('data.content');

        dump($content);

        $this->assertSame('therapist', $content['cta_link_kind'] ?? '(missing)');
    }
}
