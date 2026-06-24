<?php

namespace Database\Seeders;

use App\Enums\ServiceType;
use App\Models\ServiceCategory;
use Database\Seeders\Concerns\ImportsMedia;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    use ImportsMedia;

    public function run(): void
    {
        $img = fn (string $id, string $ixid, string $name): ?int => $this->media(
            "https://images.unsplash.com/{$id}?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid={$ixid}&ixlib=rb-4.1.0&q=80&w=1080",
            $name,
        );

        $categories = [
            [
                'slug' => 'fyzioterapie',
                'name' => 'Fyzioterapie',
                'type' => ServiceType::Physiotherapy,
                'description' => 'Specializovaná fyzioterapie pro ženy i muže. Vstupní vyšetření, kontrolní terapie, individuální přístup.',
                'hero_image' => $img('photo-1650044252595-cacd425982ff', 'M3w4NDM0ODN8MHwxfHJhbmRvbXx8fHx8fHx8fDE3NzUyMjMyMTV8', 'home-service-fyzioterapie'),
            ],
            [
                'slug' => 'relaxace',
                'name' => 'Relaxace',
                'type' => ServiceType::Massage,
                'description' => 'Relaxační, těhotenské a lymfatické masáže. Bylinná napářka a další relaxační rituály.',
                'hero_image' => $img('photo-1671493235081-5842463637cd', 'M3w4NDM0ODN8MHwxfHJhbmRvbXx8fHx8fHx8fDE3NzUyMjMyMTV8', 'home-service-masaze'),
            ],
            [
                'slug' => 'pristrojova-terapie',
                'name' => 'Přístrojová terapie',
                'type' => null,
                'description' => 'Přístrojová terapie pro urychlení hojení, úlevu od bolesti a redukci otoků.',
                'hero_image' => $img('photo-1576770075856-86b01944b92b', 'M3w4NDM0ODN8MHwxfHJhbmRvbXx8fHx8fHx8fDE3NzUyMjMyMTd8', 'home-service-laser'),
            ],
        ];

        foreach ($categories as $category) {
            ServiceCategory::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'type' => $category['type'],
                    'description' => $category['description'],
                    'hero_image' => $category['hero_image'],
                    'published_at' => now(),
                ],
            );
        }
    }
}
