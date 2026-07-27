<?php

namespace Tests\Feature;

use App\Enums\BannerType;
use App\Filament\Clusters\Obsah\Resources\Banners\Pages\EditBanner;
use App\Models\Banner;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BannerResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_edit_page_saves_through_the_nested_grid_layout(): void
    {
        $banner = Banner::factory()->create([
            'type' => BannerType::Topbar,
            'name' => 'Původní název',
            'content' => ['text' => 'Původní text'],
        ]);

        Livewire::test(EditBanner::class, ['record' => $banner->getKey()])
            ->fillForm([
                'name' => 'Nový název',
                'content.text' => 'Nový text',
                'sort_order' => 5,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $banner->refresh();

        $this->assertSame('Nový název', $banner->name);
        $this->assertSame('Nový text', $banner->content['text']);
        $this->assertSame(5, $banner->sort_order);
    }

    public function test_secondary_header_actions_are_grouped_into_a_dropdown(): void
    {
        $banner = Banner::factory()->create();

        $headerActions = Livewire::test(EditBanner::class, ['record' => $banner->getKey()])
            ->assertActionExists('saveHeader')
            ->instance()
            ->getCachedHeaderActions();

        $this->assertCount(2, $headerActions);
        $this->assertInstanceOf(Action::class, $headerActions[0]);
        $this->assertSame('saveHeader', $headerActions[0]->getName());

        $this->assertInstanceOf(ActionGroup::class, $headerActions[1]);
        $this->assertSame('Další akce', $headerActions[1]->getLabel());

        $this->assertSame(
            ['activityLog', 'delete', 'forceDelete', 'restore'],
            array_map(
                fn (Action $action): string => $action->getName(),
                array_values($headerActions[1]->getFlatActions()),
            ),
        );
    }
}
