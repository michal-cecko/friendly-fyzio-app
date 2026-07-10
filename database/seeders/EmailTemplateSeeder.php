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
                $this->brick('email-callout', ['variant' => 'info', 'icon' => 'clock', 'text' => '<p>Potvrďte prosím svou účast kliknutím na tlačítko níže.</p>']),
                $this->detailsBrick('default', 'Detaily rezervace', $this->reservationRows()),
                $this->manageButtonBrick('Potvrdit rezervaci'),
                $this->brick('email-note', ['text' => '<p>Pokud jste rezervaci nevytvořili, tento e-mail prosím ignorujte.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::ReservationConfirmed => [
                $this->brick('email-greeting', ['text' => '<p>Skvělá zpráva, {{ jmeno }}!</p>']),
                $this->brick('email-callout', ['variant' => 'success', 'icon' => 'circle-check', 'text' => '<p>Vaše rezervace je potvrzena.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Děkujeme za potvrzení účasti. Těšíme se na vás!</p>']),
                $this->detailsBrick('default', 'Detaily rezervace', $this->reservationRows()),
                $this->manageButtonBrick('Spravovat rezervaci'),
                $this->brick('email-note', ['text' => '<p>Storno zdarma je možné nejpozději 24 hodin před termínem návštěvy. Pozdější zrušení je zpoplatněno dle storno podmínek.</p>']),
                $this->brick('email-callout', ['variant' => 'info', 'icon' => 'bell', 'text' => '<p>Připomínku termínu vám pošleme 24 hodin před návštěvou.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::ReservationReminder => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Připomínáme vám, že zítra máte naplánovanou návštěvu v naší klinice. Těšíme se na vás!</p>']),
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
                $this->brick('email-paragraph', ['text' => '<p>Vaše rezervace byla automaticky zrušena, protože jste do 24 hodin před termínem nepotvrdili svou účast.</p>']),
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
