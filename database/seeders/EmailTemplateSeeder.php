<?php

namespace Database\Seeders;

use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (EmailTemplateKey::cases() as $key) {
            EmailTemplate::updateOrCreate(
                ['key' => $key->value],
                [
                    'name' => $key->label(),
                    'subject' => $key->defaultSubject(),
                    'content' => $this->contentFor($key),
                ],
            );
        }
    }

    /**
     * @return array<int, array{type: string, attrs: array{id: string, config: array<string, mixed>}}>
     */
    private function contentFor(EmailTemplateKey $key): array
    {
        return match ($key) {
            EmailTemplateKey::ReservationPending => [
                $this->brick('email-greeting', ['text' => '<p>Děkujeme za vaši rezervaci, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Prosíme o potvrzení vaší rezervace. Bez potvrzení bude termín automaticky uvolněn.</p>']),
                $this->brick('email-callout', ['variant' => 'info', 'icon' => 'clock', 'text' => '<p>Potvrďte prosím svou účast nejpozději {{ auto_zruseni_hodin }} hodin před termínem, jinak bude rezervace automaticky zrušena.</p>']),
                $this->detailsBrick('default', 'Detaily rezervace', $this->reservationRows()),
                $this->manageButtonBrick('Potvrdit rezervaci'),
                $this->brick('email-note', ['text' => '<p>Pokud jste rezervaci nevytvořili, tento e-mail prosím ignorujte.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::ReservationCreated => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Děkujeme za vaši rezervaci — níže naleznete její souhrn.</p>']),
                $this->detailsBrick('default', 'Detaily rezervace', $this->reservationRows()),
                $this->manageButtonBrick('Spravovat rezervaci'),
                $this->brick('email-note', ['text' => '<p>Blíže k termínu vás e-mailem požádáme o potvrzení účasti.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::ReservationAutoConfirmed => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-callout', ['variant' => 'success', 'icon' => 'circle-check', 'text' => '<p>Vaše rezervace je potvrzena.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Vzhledem k tomu, že se termín rychle blíží, potvrdili jsme vaši rezervaci rovnou za vás. Těšíme se na vás!</p>']),
                $this->detailsBrick('default', 'Detaily rezervace', $this->reservationRows()),
                $this->manageButtonBrick('Spravovat rezervaci'),
                $this->brick('email-note', ['text' => '<p>Zrušení potvrzené rezervace je zpoplatněno storno poplatkem {{ storno_procenta }} % z ceny služby. Pokud nemůžete přijít, dejte nám prosím vědět co nejdříve.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::ReservationConfirmed => [
                $this->brick('email-greeting', ['text' => '<p>Skvělá zpráva, {{ jmeno }}!</p>']),
                $this->brick('email-callout', ['variant' => 'success', 'icon' => 'circle-check', 'text' => '<p>Vaše rezervace je potvrzena.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Děkujeme za potvrzení účasti. Těšíme se na vás!</p>']),
                $this->detailsBrick('default', 'Detaily rezervace', $this->reservationRows()),
                $this->manageButtonBrick('Spravovat rezervaci'),
                $this->brick('email-note', ['text' => '<p>Zrušení potvrzené rezervace je zpoplatněno storno poplatkem {{ storno_procenta }} % z ceny služby. Pokud nemůžete přijít, dejte nám prosím vědět co nejdříve.</p>']),
                $this->brick('email-callout', ['variant' => 'info', 'icon' => 'bell', 'text' => '<p>Připomínku termínu vám pošleme {{ pripominka_hodin }} hodin před návštěvou.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::ReservationReminder => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Připomínáme vám, že za {{ pripominka_hodin }} hodin máte naplánovanou návštěvu v naší klinice. Těšíme se na vás!</p>']),
                $this->detailsBrick('default', 'Vaše návštěva', $this->reservationRows()),
                $this->brick('email-checklist', [
                    'title' => 'Příprava na návštěvu',
                    'items' => [
                        'Vezměte si pohodlné oblečení',
                        'Přineste si výsledky předchozích vyšetření',
                        'Dostavte se prosím 10 minut před termínem',
                    ],
                ]),
                $this->manageButtonBrick('Spravovat rezervaci'),
                $this->brick('email-note', ['text' => '<p>Pokud se na termín nemůžete dostavit, dejte nám prosím co nejdříve vědět.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::ReservationCancelled => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Vaše rezervace byla úspěšně zrušena. Níže naleznete přehled zrušené návštěvy.</p>']),
                $this->detailsBrick('danger', 'Zrušená návštěva', $this->cancelledRows()),
                $this->brick('email-paragraph', ['text' => '<p>Mrzí nás, že jste museli zrušit svou návštěvu. Pokud si budete přát, můžete si kdykoliv zarezervovat nový termín.</p>']),
                $this->rebookButtonBrick(),
                $this->replyCallout(),
            ],
            EmailTemplateKey::ReservationChanged => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Vaše rezervace byla změněna. Zkontrolujte prosím nové údaje níže.</p>']),
                $this->detailsBrick('muted', 'Původní termín', [
                    ['Služba:', '{{ puvodni_sluzba }}'],
                    ['Terapeut:', '{{ puvodni_terapeut }}'],
                    ['Datum a čas:', '{{ puvodni_termin }}'],
                ]),
                $this->detailsBrick('success', 'Nový termín', [
                    ['Služba:', '{{ sluzba }}'],
                    ['Terapeut:', '{{ terapeut }}'],
                    ['Telefon:', '{{ telefon }}'],
                    ['E-mail:', '{{ email }}'],
                    ['Datum a čas:', '{{ termin }}'],
                ]),
                $this->manageButtonBrick('Spravovat rezervaci'),
                $this->replyCallout(),
            ],
            EmailTemplateKey::ReservationAutoCancelled => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Vaše rezervace byla automaticky zrušena, protože jste do {{ auto_zruseni_hodin }} hodin před termínem nepotvrdili svou účast.</p>']),
                $this->detailsBrick('danger', 'Zrušený termín', $this->cancelledRows()),
                $this->brick('email-paragraph', ['text' => '<p>Mrzí nás, že váš termín propadl. Pokud máte stále zájem o návštěvu, můžete si níže rezervovat nový termín.</p>']),
                $this->rebookButtonBrick(),
                $this->replyCallout(),
            ],
            EmailTemplateKey::ReservationStornoPayment => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Vaši rezervaci jsme zrušili. Protože ke zrušení došlo v době kratší, než umožňuje bezplatná storno lhůta, je účtován storno poplatek.</p>']),
                $this->detailsBrick('muted', 'Stornovaná návštěva', $this->stornoRows()),
                $this->brick('email-payment', ['title' => 'Údaje k platbě', 'show_qr' => true]),
                $this->brick('email-note', ['text' => '<p>Platbu prosím uhraďte co nejdříve – naskenováním QR kódu ve své bankovní aplikaci, nebo bankovním převodem na uvedený účet s variabilním symbolem.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::ReservationDoctorNote => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Vaši rezervaci jsme zrušili. Uvedli jste, že důvodem byla nemoc a doložíte potvrzení od lékaře – v takovém případě vám storno poplatek účtovat nebudeme.</p>']),
                $this->detailsBrick('muted', 'Stornovaná návštěva', $this->stornoRows()),
                $this->brick('email-callout', ['variant' => 'info', 'icon' => 'file-text', 'text' => '<p>Potvrzení od lékaře nám prosím doručte co nejdříve. Do jeho doručení evidujeme storno jako neuhrazené.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::ReservationUnpaid => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-callout', ['variant' => 'warning', 'icon' => 'circle-alert', 'text' => '<p>Máte nezaplacený termín.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Dovolujeme si vás požádat o uhrazení návštěvy, kterou evidujeme jako nezaplacenou. Platbu prosím proveďte naskenováním QR kódu, nebo převodem na uvedený účet s variabilním symbolem.</p>']),
                $this->detailsBrick('muted', 'Detail návštěvy', $this->stornoRows()),
                $this->brick('email-payment', ['title' => 'Platební údaje', 'show_qr' => true, 'show_due' => true]),
                $this->brick('email-note', ['text' => '<p>Pokud jste již zaplatili, děkujeme a tento e-mail prosím ignorujte.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::ReservationNoShow => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-callout', ['variant' => 'danger', 'icon' => 'circle-alert', 'text' => '<p>Nedostavili jste se na potvrzený termín.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Bohužel jsme vás na potvrzeném termínu nezastihli. V souladu s našimi storno podmínkami je za nedostavení bez omluvy účtován poplatek, který prosím uhraďte QR platbou nebo převodem na uvedený účet.</p>']),
                $this->detailsBrick('danger', 'Termín, na který jste se nedostavili', $this->stornoRows()),
                $this->brick('email-payment', ['title' => 'Úhrada poplatku', 'show_qr' => true, 'show_due' => true]),
                $this->brick('email-callout', ['variant' => 'info', 'icon' => 'file-text', 'text' => '<p>Máte potvrzení od lékaře? Pokud vám v návštěvě zabránily zdravotní důvody, <a href="{{ odkaz }}">doložte nám potvrzení zde</a> a poplatek vám odpustíme.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::PaymentReceived => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }}!</p>']),
                $this->brick('email-callout', ['variant' => 'success', 'icon' => 'circle-check', 'text' => '<p>Platba byla úspěšně přijata.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Vaše platba za {{ za_co }} byla úspěšně přijata. Děkujeme!</p>']),
                $this->detailsBrick('default', 'Detail platby', [
                    ['Částka:', '{{ castka }}'],
                    ['Datum:', '{{ datum }}'],
                    ['Způsob platby:', '{{ zpusob_platby }}'],
                    ['Číslo faktury:', '{{ cislo_faktury }}'],
                ]),
                $this->brick('email-note', ['text' => '<p>Pokud byla k platbě vystavena faktura, naleznete ji ve formátu PDF v příloze tohoto e-mailu.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::PaymentOverdue => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-callout', ['variant' => 'warning', 'icon' => 'circle-alert', 'text' => '<p>Máte neuhrazenou platbu po splatnosti.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Rádi bychom vás upozornili, že jsme dosud nezaznamenali platbu za {{ za_co }}. Prosíme o co nejrychlejší zaplacení.</p>']),
                $this->brick('email-payment', ['title' => 'Platební údaje', 'show_qr' => true, 'show_due' => true]),
                $this->brick('email-note', ['text' => '<p>Pokud jste již zaplatili, dejte nám prosím vědět – platba se mohla s tímto upozorněním minout.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::InvoiceIssued => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }}!</p>']),
                $this->brick('email-paragraph', ['text' => '<p>V příloze zasíláme fakturu č. {{ cislo_faktury }} na částku {{ castka }} se splatností {{ splatnost }} za poskytnuté služby. Níže naleznete přehled položek a způsob platby.</p>']),
                $this->brick('email-invoice-items', []),
                $this->brick('email-note', ['text' => '<p>Faktura ve formátu PDF je přiložena k tomuto e-mailu.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::EmailVerification => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Děkujeme za registraci. Pro dokončení prosím potvrďte svou e-mailovou adresu kliknutím na tlačítko níže.</p>']),
                $this->brick('email-buttons', [
                    'buttons' => [
                        ['text' => 'Ověřit e-mail', 'style' => 'primary', 'link_type' => 'custom', 'url' => '{{ odkaz }}'],
                    ],
                ]),
                $this->brick('email-note', ['text' => '<p>Pokud jste si účet nevytvářeli, tento e-mail prosím ignorujte.</p>']),
            ],
            EmailTemplateKey::PasswordReset => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Obdrželi jsme žádost o obnovení hesla k vašemu účtu. Nové heslo si nastavíte kliknutím na tlačítko níže.</p>']),
                $this->brick('email-buttons', [
                    'buttons' => [
                        ['text' => 'Nastavit nové heslo', 'style' => 'primary', 'link_type' => 'custom', 'url' => '{{ odkaz }}'],
                    ],
                ]),
                $this->brick('email-note', ['text' => '<p>Pokud jste o obnovení hesla nežádali, tento e-mail prosím ignorujte – vaše heslo zůstává beze změny.</p>']),
            ],
            EmailTemplateKey::EmailChangeVerification => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Požádali jste o změnu e-mailové adresy na <strong>{{ email }}</strong>. Pro potvrzení této změny klikněte na tlačítko níže.</p>']),
                $this->brick('email-buttons', [
                    'buttons' => [
                        ['text' => 'Potvrdit novou adresu', 'style' => 'primary', 'link_type' => 'custom', 'url' => '{{ odkaz }}'],
                    ],
                ]),
                $this->brick('email-note', ['text' => '<p>Pokud jste o změnu e-mailu nežádali, tento e-mail prosím ignorujte.</p>']),
            ],
            EmailTemplateKey::AccountCreated => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>na základě vaší rezervace jsme pro vás vytvořili účet, kde si můžete spravovat své rezervace.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Heslo si nastavíte přes odkaz „Zapomenuté heslo“ na přihlašovací stránce.</p>']),
                $this->brick('email-buttons', [
                    'buttons' => [
                        ['text' => 'Přihlásit se', 'style' => 'primary', 'link_type' => 'custom', 'url' => '{{ odkaz }}'],
                    ],
                ]),
                $this->brick('email-note', ['text' => '<p>Účet využívat nemusíte — na termín se můžete dostavit i bez přihlášení.</p>']),
            ],
            EmailTemplateKey::ReviewRequest => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>rádi bychom vás poprosili o recenzi na {{ cil }}.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>{{ intro }}</p>']),
                $this->brick('email-buttons', [
                    'buttons' => [
                        ['text' => 'Napsat recenzi', 'style' => 'primary', 'link_type' => 'custom', 'url' => '{{ odkaz }}'],
                    ],
                ]),
                $this->brick('email-note', ['text' => '<p>Zabere to jen chvilku, děkujeme!</p>']),
            ],
            EmailTemplateKey::TherapistReservationCreated => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Máte novou rezervaci od klienta. Podrobnosti naleznete níže.</p>']),
                $this->detailsBrick('default', 'Detail rezervace', $this->therapistRows()),
                $this->brick('email-buttons', [
                    'buttons' => [
                        ['text' => 'Potvrdit termín', 'style' => 'primary', 'link_type' => 'custom', 'url' => '{{ odkaz_potvrdit }}'],
                        ['text' => 'Zobrazit v kalendáři', 'style' => 'soft', 'link_type' => 'custom', 'url' => '{{ odkaz }}'],
                    ],
                ]),
            ],
            EmailTemplateKey::TherapistReservationConfirmed => [
                $this->brick('email-callout', ['variant' => 'success', 'icon' => 'circle-check', 'text' => '<p>Termín s klientem {{ klient }} byl potvrzen.</p>']),
                $this->brick('email-greeting', ['text' => '<p>Děkujeme, {{ jmeno }}!</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Termín byl úspěšně potvrzen. Tento e-mail slouží jako potvrzení a záznam o potvrzeném termínu.</p>']),
                $this->detailsBrick('default', 'Potvrzený termín', $this->therapistRows()),
                $this->calendarButtonBrick(),
            ],
            EmailTemplateKey::TherapistReservationCancelled => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Klient zrušil svou rezervaci. Podrobnosti naleznete níže.</p>']),
                $this->detailsBrick('danger', 'Zrušená rezervace', [
                    ...$this->therapistRows(),
                    ['Řešení storna:', '{{ storno_reseni }}'],
                    ['Storno poplatek:', '{{ storno_castka }}'],
                ]),
                $this->calendarButtonBrick(),
            ],
            EmailTemplateKey::TherapistReservationChanged => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Klient změnil svou rezervaci. Zkontrolujte prosím nové údaje níže.</p>']),
                $this->detailsBrick('muted', 'Původní termín', [
                    ['Služba:', '{{ puvodni_sluzba }}'],
                    ['Klient:', '{{ klient }}'],
                    ['Datum a čas:', '{{ puvodni_termin }}'],
                ]),
                $this->detailsBrick('success', 'Nový termín', $this->therapistRows()),
                $this->calendarButtonBrick(),
            ],
            EmailTemplateKey::TherapistReservationAutoCancelled => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Termín klienta byl automaticky zrušen, protože klient nepotvrdil svou účast do {{ auto_zruseni_hodin }} hodin před návštěvou. Slot je nyní volný.</p>']),
                $this->detailsBrick('danger', 'Automaticky zrušený termín', [
                    ...$this->therapistRows(),
                    ['Důvod:', '{{ duvod }}'],
                ]),
                $this->calendarButtonBrick(),
            ],
            EmailTemplateKey::TherapistPaymentReceived => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den,</p>']),
                $this->brick('email-callout', ['variant' => 'success', 'icon' => 'circle-check', 'text' => '<p>Platba od klienta byla přijata.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Klient {{ klient }} uhradil platbu za {{ za_co }}.</p>']),
                $this->detailsBrick('default', 'Detail platby', [
                    ['Částka:', '{{ castka }}'],
                    ['Datum:', '{{ datum_platby }}'],
                    ['Způsob platby:', '{{ zpusob_platby }}'],
                    ['Klient:', '{{ klient }}'],
                ]),
                $this->brick('email-buttons', [
                    'buttons' => [
                        ['text' => 'Zobrazit detail klienta', 'style' => 'primary', 'link_type' => 'custom', 'url' => '{{ odkaz_klient }}'],
                    ],
                ]),
                $this->automatedNote(),
            ],
            EmailTemplateKey::TherapistPaymentOverdue => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den,</p>']),
                $this->brick('email-callout', ['variant' => 'info', 'icon' => 'triangle-alert', 'text' => '<p>Klient má neuhrazenou platbu po splatnosti.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Upozorňujeme vás, že klient {{ klient }} dosud neuhradil platbu za {{ za_co }}. Platba je po splatnosti.</p>']),
                $this->detailsBrick('danger', 'Údaje o klientovi', [
                    ['Klient:', '{{ klient }}'],
                    ['E-mail:', '{{ email_klienta }}'],
                    ['Dlužná částka:', '{{ castka }}'],
                    ['Služba:', '{{ sluzba }}'],
                    ['Splatnost do:', '{{ splatnost }}'],
                ]),
                $this->automatedNote(),
            ],
        };
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function reservationRows(): array
    {
        return [
            ['Služba:', '{{ sluzba }}'],
            ['Terapeut:', '{{ terapeut }}'],
            ['Datum a čas:', '{{ termin }}'],
            ['Místo:', '{{ misto }}'],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function cancelledRows(): array
    {
        return [
            ['Služba:', '{{ sluzba }}'],
            ['Terapeut:', '{{ terapeut }}'],
            ['Datum a čas:', '{{ termin }}'],
            ['Důvod:', '{{ duvod }}'],
        ];
    }

    /**
     * Reservation summary rows for the therapist-facing e-mails (client contact
     * details instead of therapist/place rows).
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function therapistRows(): array
    {
        return [
            ['Služba:', '{{ sluzba }}'],
            ['Klient:', '{{ klient }}'],
            ['Tel. klienta:', '{{ telefon_klienta }}'],
            ['E-mail klienta:', '{{ email_klienta }}'],
            ['Datum a čas:', '{{ termin }}'],
        ];
    }

    /**
     * Reservation summary rows for the storno e-mails (no reason/place row).
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function stornoRows(): array
    {
        return [
            ['Služba:', '{{ sluzba }}'],
            ['Terapeut:', '{{ terapeut }}'],
            ['Datum a čas:', '{{ termin }}'],
        ];
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $rows
     * @return array{type: string, attrs: array{id: string, config: array<string, mixed>}}
     */
    private function detailsBrick(string $variant, string $title, array $rows): array
    {
        return $this->brick('email-reservation-details', [
            'variant' => $variant,
            'title' => $title,
            'rows' => array_map(fn (array $row): array => ['label' => $row[0], 'value' => $row[1]], $rows),
        ]);
    }

    /**
     * The single "manage reservation" button pointing at the signed magic link
     * ({{ odkaz }}) — the one page hosting confirm / cancel / storno actions.
     *
     * @return array{type: string, attrs: array{id: string, config: array<string, mixed>}}
     */
    private function manageButtonBrick(string $text): array
    {
        return $this->brick('email-buttons', [
            'buttons' => [
                ['text' => $text, 'style' => 'primary', 'link_type' => 'custom', 'url' => '{{ odkaz }}'],
            ],
        ]);
    }

    /**
     * The therapist "open in calendar" button pointing at the reservation in the
     * admin ({{ odkaz }}).
     *
     * @return array{type: string, attrs: array{id: string, config: array<string, mixed>}}
     */
    private function calendarButtonBrick(): array
    {
        return $this->brick('email-buttons', [
            'buttons' => [
                ['text' => 'Zobrazit v kalendáři', 'style' => 'primary', 'link_type' => 'custom', 'url' => '{{ odkaz }}'],
            ],
        ]);
    }

    /**
     * The "do not reply, automated notification" note used by the therapist payment
     * e-mails.
     *
     * @return array{type: string, attrs: array{id: string, config: array<string, mixed>}}
     */
    private function automatedNote(): array
    {
        return $this->brick('email-note', [
            'text' => '<p>Na tento e-mail neodpovídejte. Jedná se o automatické oznámení.</p>',
        ]);
    }

    /**
     * A "book a new appointment" button pointing at the public reservation wizard.
     *
     * @return array{type: string, attrs: array{id: string, config: array<string, mixed>}}
     */
    private function rebookButtonBrick(): array
    {
        return $this->brick('email-buttons', [
            'buttons' => [
                ['text' => 'Zarezervovat nový termín', 'style' => 'primary', 'link_type' => 'internal', 'link_ref' => 'route:reservation.wizard'],
            ],
        ]);
    }

    /**
     * @return array{type: string, attrs: array{id: string, config: array<string, mixed>}}
     */
    private function replyCallout(): array
    {
        return $this->brick('email-callout', [
            'variant' => 'neutral',
            'icon' => 'reply',
            'text' => '<p>Vaše odpověď na tento email bude odeslána přímo terapeutovi.</p>',
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{type: string, attrs: array{id: string, config: array<string, mixed>}}
     */
    private function brick(string $id, array $config): array
    {
        return ['type' => 'masonBrick', 'attrs' => ['id' => $id, 'config' => $config]];
    }
}
