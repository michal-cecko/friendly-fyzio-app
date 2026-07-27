<?php

namespace Database\Seeders;

use App\Enums\PayableType;
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
            'key' => 'reservation.day_waitlist_enabled',
            'value' => '1',
            'type' => SettingValueType::Boolean,
            'label' => 'Pořadník na plné dny',
            'group' => 'Rezervace',
            'description' => 'Umožní klientům zapsat se do pořadníku na plně obsazený den a upozorní je e-mailem, jakmile se u terapeuta na ten den uvolní místo.',
            'config' => null,
            'sort' => 4,
        ]);

        $this->upsert([
            'key' => 'reservation.lead_time_hours',
            'value' => '2',
            'type' => SettingValueType::Integer,
            'label' => 'Minimální předstih (hodiny)',
            'group' => 'Rezervace',
            'description' => 'Minimální počet hodin před začátkem termínu, kdy je ještě možné se online objednat.',
            'config' => ['min' => 0, 'step' => 1, 'suffix' => 'h'],
            'sort' => 4,
        ]);

        $this->upsert([
            'key' => 'reservation.confirmation_hours',
            'value' => '48',
            'type' => SettingValueType::Integer,
            'label' => 'Potvrzení účasti (hodiny předem)',
            'group' => 'Rezervace',
            'description' => 'Kolik hodin před termínem se klientovi odešle e-mail s žádostí o potvrzení účasti.',
            'config' => ['min' => 1, 'step' => 1, 'suffix' => 'h'],
            'sort' => 5,
        ]);

        $this->upsert([
            'key' => 'reservation.reminder_hours',
            'value' => '24',
            'type' => SettingValueType::Integer,
            'label' => 'Připomínka termínu (hodiny předem)',
            'group' => 'Rezervace',
            'description' => 'Kolik hodin před termínem se potvrzené rezervaci odešle připomínka návštěvy.',
            'config' => ['min' => 1, 'step' => 1, 'suffix' => 'h'],
            'sort' => 6,
        ]);

        $this->upsert([
            'key' => 'reservation.auto_cancel_hours',
            'value' => '24',
            'type' => SettingValueType::Integer,
            'label' => 'Automatické zrušení (hodiny předem)',
            'group' => 'Rezervace',
            'description' => 'Pokud klient nepotvrdí účast do tohoto počtu hodin před termínem, rezervace se automaticky zruší.',
            'config' => ['min' => 1, 'step' => 1, 'suffix' => 'h'],
            'sort' => 7,
        ]);

        $this->upsert([
            'key' => 'reservation.cancel_before_hours',
            'value' => '24',
            'type' => SettingValueType::Integer,
            'label' => 'Bezplatné zrušení (hodiny předem)',
            'group' => 'Rezervace',
            'description' => 'Do kolika hodin před termínem může klient rezervaci bezplatně zrušit online. Konkrétní služba může mít vlastní storno pravidlo.',
            'config' => ['min' => 1, 'step' => 1, 'suffix' => 'h'],
            'sort' => 8,
        ]);

        $this->upsert([
            'key' => 'reservation.storno_fee_percent',
            'value' => '50',
            'type' => SettingValueType::Integer,
            'label' => 'Storno poplatek (% z ceny)',
            'group' => 'Rezervace',
            'description' => 'Výše storno poplatku jako procento z ceny služby, pokud klient ruší v storno okně a zvolí úhradu místo potvrzení od lékaře.',
            'config' => ['min' => 0, 'max' => 100, 'step' => 5, 'suffix' => '%'],
            'sort' => 9,
        ]);

        $this->upsert([
            'key' => 'payments.iban',
            'value' => 'CZ6508000000192000145399',
            'type' => SettingValueType::Text,
            'label' => 'IBAN',
            'group' => 'Platby',
            'description' => 'Číslo účtu ve formátu IBAN (např. CZ…), na které se generují QR platby.',
            'config' => null,
            'sort' => 0,
        ]);

        $this->upsert([
            'key' => 'payments.recipient_name',
            'value' => 'FriendlyFyzio s.r.o.',
            'type' => SettingValueType::Text,
            'label' => 'Název příjemce',
            'group' => 'Platby',
            'description' => 'Jméno příjemce platby zobrazené u platebních údajů.',
            'config' => null,
            'sort' => 1,
        ]);

        $this->upsert([
            'key' => 'payments.qr_message',
            'value' => 'Storno {{ sluzba }}, VS {{ vs }}',
            'type' => SettingValueType::Text,
            'label' => 'Zpráva pro příjemce',
            'group' => 'Platby',
            'description' => 'Text zprávy pro příjemce v QR platbě. Proměnné: {{ jmeno }}, {{ sluzba }}, {{ terapeut }}, {{ termin }}, {{ vs }}, {{ castka }}.',
            'config' => null,
            'sort' => 2,
        ]);

        $this->upsert([
            'key' => 'payments.due_days',
            'value' => '7',
            'type' => SettingValueType::Integer,
            'label' => 'Splatnost plateb (dny)',
            'group' => 'Platby',
            'description' => 'Výchozí splatnost vyžádaných plateb (storno poplatky, nezaplacené termíny) ode dne vytvoření.',
            'config' => ['min' => 1, 'step' => 1, 'suffix' => 'dní'],
            'sort' => 3,
        ]);

        $this->upsert([
            'key' => 'payments.no_show_fee_percent',
            'value' => '100',
            'type' => SettingValueType::Integer,
            'label' => 'Poplatek za nedostavení (% z ceny)',
            'group' => 'Platby',
            'description' => 'Výše poplatku, když se klient bez omluvy nedostaví na potvrzený termín.',
            'config' => ['min' => 0, 'max' => 100, 'step' => 5, 'suffix' => '%'],
            'sort' => 4,
        ]);

        $this->upsert([
            'key' => 'credits.expiry_notice_days',
            'value' => '7',
            'type' => SettingValueType::Integer,
            'label' => 'Upozornění na vypršení kreditu (dní předem)',
            'group' => 'Platby',
            'description' => 'Kolik dní před vypršením kreditu se klientovi odešle upozornění e-mailem. 0 = vypnuto.',
            'config' => ['min' => 0, 'step' => 1, 'suffix' => 'dní'],
            'sort' => 5,
        ]);

        $this->seedInvoicing();

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
            ['enrollments.hold_hours', '48', 'Rezervace místa (hodin)', 'Jak dlouho drží nezaplacená přihláška místo, než je automaticky zrušena a nabídnuta náhradníkům.', 'hodin', 0],
            ['enrollments.course_cancel_before_days', '7', 'Odhlášení z kurzu (dní předem)', 'Do kolika dní před začátkem série se klient může sám odhlásit v klientské zóně.', 'dní', 1],
            ['enrollments.event_cancel_before_hours', '24', 'Odhlášení z akce (hodin předem)', 'Do kolika hodin před jednorázovou akcí (lekce, workshop…) se klient může sám odhlásit.', 'hodin', 2],
            ['enrollments.waitlist_invite_hours', '24', 'Nabídka místa čekajícím (hodin)', 'V režimu „Oslovit čekající“: jak dlouho zůstane uvolněné místo rezervované pro čekací listinu, než se uvolní veřejnosti.', 'hodin', 3],
            ['substitutes.token_validity_days', '30', 'Platnost náhradního vstupu (dní)', 'Jak dlouho po včasné omluvě z lekce platí náhradní vstup.', 'dní', 4],
            ['lessons.drop_in_cutoff_hours', '2', 'Jednorázový vstup nejpozději (hodin před)', 'Jak dlouho před začátkem lekce se ještě dá koupit jednotlivé volné místo. Potom se prodej zavře, aby měl lektor finální seznam.', 'hodin', 5],
        ] as [$key, $value, $label, $description, $suffix, $sort]) {
            $this->upsert([
                'key' => $key,
                'value' => $value,
                'type' => SettingValueType::Integer,
                'label' => $label,
                'group' => 'Přihlášky',
                'description' => $description,
                'config' => ['min' => 0, 'step' => 1, 'suffix' => $suffix],
                'sort' => $sort,
            ]);
        }

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
     * The "Fakturace" group: supplier identity frozen onto invoices at issue time,
     * DPH mode, due dates, the invoice text hooks and the per-payable item templates.
     */
    private function seedInvoicing(): void
    {
        $texts = [
            ['invoices.supplier_name', 'FriendlyFyzio s.r.o.', 'Název dodavatele', null],
            ['invoices.supplier_address', 'Zednická 1109/2, Ostrava', 'Adresa dodavatele', null],
            ['invoices.supplier_dic', '', 'DIČ dodavatele', 'Vyplňte, pokud bylo přiděleno. Tiskne se v hlavičce dokladů.'],
            ['invoices.supplier_registration', '', 'Zápis v rejstříku', 'Např. „Zapsáno v OR vedeném KS v Ostravě…“ — tiskne se v patičce dokladů.'],
            ['invoices.bank_account', '', 'Číslo účtu (český formát)', 'Např. 123456789/0800. Na dokladech se zobrazuje vedle IBANu.'],
            ['invoices.text_before_items', 'Fakturujeme Vám za poskytnuté služby:', 'Text před položkami', 'Výchozí text nad položkami faktury; u každé faktury lze upravit.'],
            ['invoices.text_after_items', '', 'Text za položkami', 'Výchozí text pod položkami faktury; u každé faktury lze upravit.'],
            ['invoices.footer_thank_you', 'Děkujeme za Vaši důvěru a přejeme pevné zdraví!', 'Poděkování v patičce', 'Zvýrazněný řádek v patičce faktury.'],
            ['invoices.vat_note', 'Nejsme plátci DPH.', 'Poznámka k DPH (neplátce)', 'Tiskne se na fakturách, dokud není zapnutý režim plátce DPH.'],
        ];

        foreach ($texts as $sort => [$key, $value, $label, $description]) {
            $this->upsert([
                'key' => $key,
                'value' => $value,
                'type' => SettingValueType::Text,
                'label' => $label,
                'group' => 'Fakturace',
                'description' => $description,
                'config' => null,
                'sort' => $sort,
            ]);
        }

        $this->upsert([
            'key' => 'invoices.vat_payer',
            'value' => '0',
            'type' => SettingValueType::Boolean,
            'label' => 'Plátce DPH',
            'group' => 'Fakturace',
            'description' => 'Po zapnutí položky faktur nesou sazbu DPH a doklad obsahuje rozpis daně (ceny se berou včetně DPH).',
            'config' => null,
            'sort' => 9,
        ]);

        $this->upsert([
            'key' => 'invoices.default_vat_rate',
            'value' => '21',
            'type' => SettingValueType::Integer,
            'label' => 'Výchozí sazba DPH',
            'group' => 'Fakturace',
            'description' => null,
            'config' => ['min' => 0, 'max' => 100, 'step' => 1, 'suffix' => '%'],
            'sort' => 10,
        ]);

        $this->upsert([
            'key' => 'invoices.due_days',
            'value' => '14',
            'type' => SettingValueType::Integer,
            'label' => 'Splatnost faktur (dny)',
            'group' => 'Fakturace',
            'description' => null,
            'config' => ['min' => 1, 'step' => 1, 'suffix' => 'dní'],
            'sort' => 11,
        ]);

        $sort = 12;

        foreach (PayableType::cases() as $type) {
            $tokens = 'Proměnné: '.implode(', ', array_map(
                fn (string $token): string => '{{ '.$token.' }}',
                array_keys($type->tokens()),
            ));

            $this->upsert([
                'key' => $type->titleSettingKey(),
                'value' => $type->defaultTitleTemplate(),
                'type' => SettingValueType::Text,
                'label' => 'Položka: '.$type->label().' – název',
                'group' => 'Fakturace',
                'description' => $tokens,
                'config' => null,
                'sort' => $sort++,
            ]);

            $this->upsert([
                'key' => $type->descriptionSettingKey(),
                'value' => $type->defaultDescriptionTemplate(),
                'type' => SettingValueType::Text,
                'label' => 'Položka: '.$type->label().' – popis',
                'group' => 'Fakturace',
                'description' => $tokens,
                'config' => null,
                'sort' => $sort++,
            ]);
        }

        $stornoTokens = 'Proměnné: '.implode(', ', array_map(
            fn (string $token): string => '{{ '.$token.' }}',
            array_keys(PayableType::Reservation->tokens()),
        ));

        $this->upsert([
            'key' => PayableType::STORNO_TITLE_KEY,
            'value' => PayableType::STORNO_TITLE_DEFAULT,
            'type' => SettingValueType::Text,
            'label' => 'Položka: Storno poplatek – název',
            'group' => 'Fakturace',
            'description' => $stornoTokens,
            'config' => null,
            'sort' => $sort++,
        ]);

        $this->upsert([
            'key' => PayableType::STORNO_DESCRIPTION_KEY,
            'value' => PayableType::STORNO_DESCRIPTION_DEFAULT,
            'type' => SettingValueType::Text,
            'label' => 'Položka: Storno poplatek – popis',
            'group' => 'Fakturace',
            'description' => $stornoTokens,
            'config' => null,
            'sort' => $sort,
        ]);
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
