<?php

namespace Database\Seeders;

use App\Models\EventCategory;
use Illuminate\Database\Seeder;

/**
 * The two canonical one-off event categories. Every category is a public
 * landing page at /{slug}; more (e.g. přednášky) can be added in the admin.
 */
class EventCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug' => 'workshopy',
                'name' => 'Workshopy',
                'description' => 'Tematické workshopy pod vedením našich terapeutů — praktické techniky, které si odnesete domů.',
                'display_order' => 1,
            ],
            [
                'slug' => 'jednorazove-lekce',
                'name' => 'Jednorázové lekce',
                'description' => 'Vyzkoušejte si lekci bez závazku celého kurzu — jednorázový vstup na jedno cvičení.',
                'display_order' => 2,
            ],
        ];

        foreach ($categories as $category) {
            EventCategory::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'display_order' => $category['display_order'],
                    'published_at' => now(),
                ],
            );
        }
    }
}
