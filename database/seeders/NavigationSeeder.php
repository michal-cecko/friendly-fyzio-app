<?php

namespace Database\Seeders;

use App\Enums\NavigationLocation;
use App\Models\Navigation;
use App\Models\ServiceCategory;
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
                ['label' => 'Fyzioterapie', 'ref' => 'fyzioterapie'],
                ['label' => 'Přístrojová terapie', 'ref' => 'pristrojova-terapie'],
                ['label' => 'Masáže a relaxace', 'ref' => 'relaxace'],
            ]],
            ['label' => 'Kurzy', 'url' => '/kurzy'],
            ['label' => 'Kontakt', 'url' => '/kontakt'],
        ]);

        $footer = Navigation::create(['location' => NavigationLocation::Footer->value]);
        $this->addItems($footer, [
            ['label' => 'Služby', 'children' => [
                ['label' => 'Fyzioterapie', 'ref' => 'fyzioterapie'],
                ['label' => 'Pohybové kurzy', 'url' => '/kurzy'],
                ['label' => 'Masáže a relaxace', 'ref' => 'relaxace'],
                ['label' => 'Laser / kryoterapie', 'ref' => 'pristrojova-terapie'],
                ['label' => 'Workshopy', 'url' => '/kurzy'],
            ]],
            ['label' => 'Info', 'children' => [
                ['label' => 'Ceník', 'url' => '/sluzby'],
                ['label' => 'O nás', 'url' => '/o-nas'],
                ['label' => 'Dárkové poukazy', 'url' => '/sluzby'],
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
                ...$this->linkAttributes($item),
                'label' => $item['label'],
                'target' => '_self',
                'display_order' => $order,
            ]);

            foreach ($children as $childOrder => $child) {
                $record->children()->create([
                    ...$this->linkAttributes($child),
                    'label' => $child['label'],
                    'target' => '_self',
                    'display_order' => $childOrder,
                ]);
            }
        }
    }

    /**
     * Build the link columns for an item: an internal category reference when a
     * `ref` (category slug) is given, otherwise a plain custom URL.
     *
     * @param  array<string, mixed>  $item
     * @return array{link_type: string, link_ref: ?string, url: ?string}
     */
    private function linkAttributes(array $item): array
    {
        if (! empty($item['ref'])) {
            $id = ServiceCategory::where('slug', $item['ref'])->value('id');

            if ($id !== null) {
                return ['link_type' => 'internal', 'link_ref' => "category:{$id}", 'url' => null];
            }
        }

        return ['link_type' => 'custom', 'link_ref' => null, 'url' => $item['url'] ?? null];
    }
}
