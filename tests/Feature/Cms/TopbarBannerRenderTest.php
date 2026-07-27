<?php

namespace Tests\Feature\Cms;

use App\Enums\BannerType;
use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopbarBannerRenderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $content
     */
    private function renderTopbar(array $content): string
    {
        $banner = Banner::factory()->make([
            'id' => 'topbar-test',
            'type' => BannerType::Topbar,
            'content' => $content,
        ]);

        return view('components.banners.topbar', ['banner' => $banner])->render();
    }

    public function test_content_row_reserves_space_for_the_dismiss_button(): void
    {
        $html = $this->renderTopbar([
            'text' => 'Právě probíhá přihlašování na lekce a kurzy leden–duben!',
            'cta_text' => 'Přihlásit se',
            'cta_link_type' => 'custom',
            'cta_url' => 'https://example.com/prihlaseni',
        ]);

        // The dismiss button is absolutely positioned, so the row needs padding
        // on the right — otherwise the CTA runs underneath it on narrow screens.
        $this->assertStringContainsString('pr-12', $html);
        $this->assertStringContainsString('sm:pr-14', $html);

        // Left-aligned and wrapping on mobile, centred from the sm breakpoint up.
        $this->assertStringContainsString('flex-wrap', $html);
        $this->assertStringContainsString('text-left', $html);
        $this->assertStringContainsString('sm:text-center', $html);
    }

    public function test_cta_arrow_is_dropped_when_a_custom_icon_is_set(): void
    {
        $withIcon = $this->renderTopbar([
            'text' => 'Zápis běží',
            'cta_text' => 'Přihlásit se',
            'cta_icon' => 'lucide-send',
            'cta_link_type' => 'custom',
            'cta_url' => 'https://example.com/prihlaseni',
        ]);

        $this->assertStringNotContainsString('&rarr;', $withIcon);

        $withoutIcon = $this->renderTopbar([
            'text' => 'Zápis běží',
            'cta_text' => 'Přihlásit se',
            'cta_link_type' => 'custom',
            'cta_url' => 'https://example.com/prihlaseni',
        ]);

        $this->assertStringContainsString('&rarr;', $withoutIcon);
    }
}
