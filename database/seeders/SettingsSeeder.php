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

        $this->upsert([
            'key' => 'newsletter.mailerlite_group_id',
            'value' => '',
            'type' => SettingValueType::Text,
            'label' => 'MailerLite skupina (ID)',
            'group' => 'Newsletter',
            'description' => 'ID skupiny (audience) v MailerLite, do které se přidávají odběratelé z webu.',
            'config' => null,
            'sort' => 0,
        ]);

        $this->upsert([
            'key' => 'reviews.enabled',
            'value' => '0',
            'type' => SettingValueType::Boolean,
            'label' => 'Automatické žádosti o recenzi',
            'group' => 'Recenze',
            'description' => 'Zapíná automatické e-maily s prosbou o recenzi po skončení kurzů a workshopů.',
            'config' => null,
            'sort' => 0,
        ]);

        $this->upsert([
            'key' => 'reviews.days_after',
            'value' => '2',
            'type' => SettingValueType::Integer,
            'label' => 'Odeslat po (dnech)',
            'group' => 'Recenze',
            'description' => 'Kolik dní po skončení akce se má automaticky odeslat žádost o recenzi.',
            'config' => ['min' => 0, 'step' => 1, 'suffix' => 'dní'],
            'sort' => 1,
        ]);

        $this->upsert([
            'key' => 'reviews.email_subject',
            'value' => 'Jak jste byli spokojeni?',
            'type' => SettingValueType::Text,
            'label' => 'Předmět e-mailu',
            'group' => 'Recenze',
            'description' => null,
            'config' => null,
            'sort' => 3,
        ]);

        $this->upsert([
            'key' => 'reviews.email_intro',
            'value' => 'Budeme moc rádi, když nám k akci zanecháte krátkou recenzi. Zabere to jen chvilku.',
            'type' => SettingValueType::Text,
            'label' => 'Úvodní text e-mailu',
            'group' => 'Recenze',
            'description' => null,
            'config' => null,
            'sort' => 4,
        ]);

        foreach ([
            ['web.contact_email', 'info@friendlyfyzio.cz', 'Kontaktní e-mail'],
            ['web.contact_phone', '+420 604 793 255', 'Telefon'],
            ['web.address', 'Zednická 1109/2, Ostrava', 'Adresa'],
            ['web.opening_hours', 'Po–Pá 8:00–17:00', 'Otevírací doba'],
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
