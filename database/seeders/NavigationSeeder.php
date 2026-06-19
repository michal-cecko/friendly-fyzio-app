<?php

namespace Database\Seeders;

use App\Enums\NavigationLocation;
use App\Models\Navigation;
use Illuminate\Database\Seeder;

class NavigationSeeder extends Seeder
{
    public function run(): void
    {
        Navigation::query()
            ->whereIn('location', [NavigationLocation::Header->value, NavigationLocation::Footer->value])
            ->get()
            ->each
            ->delete();

        $header = Navigation::create(['location' => NavigationLocation::Header->value]);
        $this->addItems($header, [
            ['label' => 'Domů', 'url' => '/'],
            ['label' => 'O nás', 'url' => '/o-nas'],
            ['label' => 'Služby', 'url' => '/sluzby', 'children' => [
                ['label' => 'Fyzioterapie', 'url' => '/sluzby'],
                ['label' => 'Přístrojová terapie', 'url' => '/sluzby'],
                ['label' => 'Relaxace', 'url' => '/sluzby'],
            ]],
            ['label' => 'Kurzy', 'url' => '/kurzy'],
            ['label' => 'Kontakt', 'url' => '/kontakt'],
        ]);

        $footer = Navigation::create(['location' => NavigationLocation::Footer->value]);
        $this->addItems($footer, [
            ['label' => 'Služby', 'children' => [
                ['label' => 'Fyzioterapie', 'url' => '/sluzby'],
                ['label' => 'Přístrojová terapie', 'url' => '/sluzby'],
                ['label' => 'Kurzy', 'url' => '/kurzy'],
                ['label' => 'Relaxace', 'url' => '/sluzby'],
            ]],
            ['label' => 'Klinika', 'children' => [
                ['label' => 'O nás', 'url' => '/o-nas'],
                ['label' => 'Reference', 'url' => '/o-nas'],
                ['label' => 'Kontakt', 'url' => '/kontakt'],
            ]],
            ['label' => 'Informace', 'children' => [
                ['label' => 'Ceník', 'url' => '/sluzby'],
                ['label' => 'Časté dotazy', 'url' => '#'],
                ['label' => 'Ochrana soukromí', 'url' => '#'],
            ]],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function addItems(Navigation $navigation, array $items): void
    {
        foreach ($items as $order => $item) {
            $children = $item['children'] ?? [];

            $record = $navigation->items()->create([
                'label' => $item['label'],
                'link_type' => 'custom',
                'url' => $item['url'] ?? null,
                'target' => '_self',
                'display_order' => $order,
            ]);

            foreach ($children as $childOrder => $child) {
                $record->children()->create([
                    'label' => $child['label'],
                    'link_type' => 'custom',
                    'url' => $child['url'] ?? null,
                    'target' => '_self',
                    'display_order' => $childOrder,
                ]);
            }
        }
    }
}
