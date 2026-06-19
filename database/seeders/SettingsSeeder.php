<?php

namespace Database\Seeders;

use App\Enums\SettingValueType;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $this->upsert([
            'key' => 'reservation.block_minutes',
            'value' => '15',
            'type' => SettingValueType::Integer,
            'label' => 'Délka jednoho bloku (min)',
            'group' => 'Rezervace',
            'description' => 'Základní délka časového bloku. Ovlivňuje kalendář i krok délky služeb.',
            'config' => ['min' => 5, 'step' => 5, 'suffix' => 'min'],
            'sort' => 0,
        ]);

        foreach ([
            ['web.site_name', 'Friendly Fyzio', 'Název webu'],
            ['web.contact_email', 'info@friendlyfyzio.cz', 'Kontaktní e-mail'],
            ['web.contact_phone', '+420 777 123 456', 'Telefon'],
            ['web.address', 'Zdravá 12, 110 00 Praha', 'Adresa'],
            ['web.instagram_url', 'https://instagram.com/friendlyfyzio', 'Instagram URL'],
            ['web.facebook_url', 'https://facebook.com/friendlyfyzio', 'Facebook URL'],
            ['web.footer_note', 'Komplexní fyzioterapie a péče o ženské zdraví.', 'Text v patičce'],
        ] as $sort => [$key, $value, $label]) {
            $this->upsert([
                'key' => $key,
                'value' => $value,
                'type' => SettingValueType::Text,
                'label' => $label,
                'group' => 'Web',
                'description' => null,
                'config' => null,
                'sort' => $sort,
            ]);
        }
    }

    /**
     * @param  array{key: string, value: ?string, type: SettingValueType, label: string, group: ?string, description: ?string, config: ?array<string, mixed>, sort: int}  $attributes
     */
    private function upsert(array $attributes): void
    {
        Setting::updateOrCreate(
            ['key' => $attributes['key']],
            $attributes,
        );
    }
}
