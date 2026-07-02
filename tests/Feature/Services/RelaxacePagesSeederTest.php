<?php

namespace Tests\Feature\Services;

use App\Enums\ServiceVisibility;
use App\Models\Service;
use App\Models\ServiceCategory;
use Database\Seeders\ServicePagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the real ServicePagesSeeder for the Relaxace category and its four
 * massage detail pages. Image downloads degrade gracefully (null on failure),
 * so these text assertions do not depend on network access.
 */
class RelaxacePagesSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedRelaxace(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => 'relaxace', 'name' => 'Relaxace']);

        $services = [
            ['slug' => 'lymfaticke-masaze', 'name' => 'Lymfatické masáže', 'visibility' => ServiceVisibility::Public],
            ['slug' => 'tehotenske-masaze', 'name' => 'Těhotenské masáže', 'visibility' => ServiceVisibility::Public],
            ['slug' => 'masaze-miminek-a-deti', 'name' => 'Masáže miminek a dětí', 'visibility' => ServiceVisibility::Public],
            ['slug' => 'bylinna-naparka', 'name' => 'Bylinná napářka', 'visibility' => ServiceVisibility::Hidden],
        ];

        foreach ($services as $service) {
            Service::factory()->create([
                'category_id' => $category->id,
                'slug' => $service['slug'],
                'name' => $service['name'],
                'visibility' => $service['visibility'],
                'published_at' => now(),
            ]);
        }

        $this->seed(ServicePagesSeeder::class);
    }

    public function test_category_landing_page_renders(): void
    {
        $this->seedRelaxace();

        $this->get('/sluzby/relaxace')
            ->assertOk()
            ->assertSee('Masáže a relaxace')
            ->assertSee('Manuální lymfatické masáže')
            ->assertSee('Masáže miminek a dětí')
            ->assertSee('Bylinná napářka')
            ->assertSee('Dopřejte si masáž či relaxační rituál');
    }

    public function test_lymphatic_massage_page_renders_new_brick_and_pricing(): void
    {
        $this->seedRelaxace();

        $this->get('/sluzby/relaxace/lymfaticke-masaze')
            ->assertOk()
            ->assertSee('Manuální lymfatické masáže')
            ->assertSee('Lymfatický systém a jeho význam')
            ->assertSee('Kontraindikace')                       // text-list warning card
            ->assertSee('Komu pomůže lymfatická masáž?')        // feature-cards
            ->assertSee('1 100 Kč');                            // pricing
    }

    public function test_pregnancy_and_baby_massage_pages_render(): void
    {
        $this->seedRelaxace();

        $this->get('/sluzby/relaxace/tehotenske-masaze')
            ->assertOk()
            ->assertSee('Těhotenské masáže')
            ->assertSee('Pohodová uvolněná maminka');

        $this->get('/sluzby/relaxace/masaze-miminek-a-deti')
            ->assertOk()
            ->assertSee('Masáže miminek a dětí')
            ->assertSee('Dotek je první řečí lásky');
    }

    public function test_herbal_steam_page_renders_soft_card_price_note_and_phone_cta(): void
    {
        $this->seedRelaxace();

        // Hidden service, but its published custom page is served to the public.
        $this->get('/sluzby/relaxace/bylinna-naparka')
            ->assertOk()
            ->assertSee('Bylinná napářka')
            ->assertSee('href="tel:+420604791215"', false)      // phone CTA
            ->assertSee('Jemné napařování')                     // text-list soft card
            ->assertSee('Nabízíme tři varianty napářky')
            ->assertSee('cca 60 minut')
            ->assertSee('bylinný čaj');                         // pricing footer note
    }
}
