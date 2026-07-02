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

        $this->upsert([
            'key' => 'reservation.reactivation_months',
            'value' => '12',
            'type' => SettingValueType::Integer,
            'label' => 'Reaktivace po (měsíce)',
            'group' => 'Rezervace',
            'description' => 'Po kolika měsících nečinnosti musí přihlášený klient potvrdit své údaje, než se znovu objedná.',
            'config' => ['min' => 1, 'step' => 1, 'suffix' => 'měs.'],
            'sort' => 1,
        ]);

        $this->upsert([
            'key' => 'reservation.default_existing_client_months',
            'value' => '6',
            'type' => SettingValueType::Integer,
            'label' => 'Okno stávajícího klienta (měsíce)',
            'group' => 'Rezervace',
            'description' => 'Výchozí doba, po kterou je klient považován za stávajícího u služeb „pro klienty“ bez vlastního nastavení.',
            'config' => ['min' => 1, 'step' => 1, 'suffix' => 'měs.'],
            'sort' => 2,
        ]);

        $this->upsert([
            'key' => 'reservation.booking_window_days',
            'value' => '60',
            'type' => SettingValueType::Integer,
            'label' => 'Okno rezervací (dny)',
            'group' => 'Rezervace',
            'description' => 'Jak daleko dopředu může klient online rezervovat termín.',
            'config' => ['min' => 1, 'step' => 1, 'suffix' => 'dní'],
            'sort' => 3,
        ]);

        $this->upsert([
            'key' => 'reservation.lead_time_hours',
            'value' => '0',
            'type' => SettingValueType::Integer,
            'label' => 'Minimální předstih (hodiny)',
            'group' => 'Rezervace',
            'description' => 'Minimální počet hodin před začátkem termínu, kdy je ještě možné se online objednat.',
            'config' => ['min' => 0, 'step' => 1, 'suffix' => 'h'],
            'sort' => 4,
        ]);

        foreach ([
            ['web.contact_email', 'info@friendlyfyzio.cz', 'Kontaktní e-mail'],
            ['web.contact_phone', '+420 604 793 255', 'Telefon'],
            ['web.address', 'Zednická 1109/2, Ostrava', 'Adresa'],
            ['web.instagram_url', 'https://instagram.com/friendlyfyzio', 'Instagram URL'],
            ['web.facebook_url', 'https://facebook.com/friendlyfyzio', 'Facebook URL'],
            ['web.footer_note', 'Specializovaná fyzioterapie pro ženy i muže. Individuální přístup k vašemu zdraví.', 'Text v patičce'],
            ['web.company_id', '06816967', 'IČO'],
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
