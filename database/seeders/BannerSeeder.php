<?php

namespace Database\Seeders;

use App\Enums\BannerType;
use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        Banner::withTrashed()->forceDelete();

        Banner::create([
            'name' => 'Zápis na kurzy',
            'type' => BannerType::Topbar,
            'placement' => 'all',
            'is_active' => true,
            'sort_order' => 10,
            'content' => [
                'text' => 'Právě probíhá přihlašování na lekce a kurzy leden–duben!',
                'cta_text' => 'Přihlásit se',
                'cta_url' => '/kurzy',
            ],
        ]);

        Banner::create([
            'name' => 'Plovoucí okno – dotaz',
            'type' => BannerType::Floating,
            'placement' => 'all',
            'is_active' => false,
            'content' => [
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'title' => 'Máte dotaz?',
                'description' => 'Rádi vám poradíme s výběrem vhodné terapie.',
                'cta_text' => 'Kontaktujte nás',
                'cta_url' => '/kontakt',
            ],
        ]);

        Banner::create([
            'name' => 'Vyskakovací okno – novinka',
            'type' => BannerType::Popup,
            'placement' => 'all',
            'is_active' => false,
            'content' => [
                'badge_text' => 'Novinka',
                'title' => 'Nový kurz hormonální jógy',
                'description' => 'Otevíráme nový kurz. Rezervujte si své místo včas.',
                'cta_text' => 'Zjistit více',
                'cta_url' => '/kurzy',
            ],
        ]);
    }
}
