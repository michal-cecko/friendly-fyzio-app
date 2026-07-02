<?php

namespace Tests\Feature\Cms;

use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_menu_item_renders_custom_url_and_target(): void
    {
        Page::factory()->system('home')->create(['slug' => '/']);

        $navigation = Navigation::create(['location' => 'header']);
        NavigationItem::create([
            'navigation_id' => $navigation->id,
            'label' => 'Rezervace',
            'link_type' => 'custom',
            'url' => '/rezervace',
            'target' => '_blank',
            'display_order' => 0,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Rezervace')
            ->assertSee('href="/rezervace"', false)
            ->assertSee('target="_blank"', false);
    }
}
