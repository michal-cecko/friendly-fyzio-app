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
                $this->brick('email-paragraph', ['text' => '<p>Vaše rezervace zatím čeká na potvrzení od terapeuta. Jakmile ji terapeut potvrdí, dáme vám vědět.</p>']),
                $this->brick('email-callout', ['variant' => 'info', 'icon' => 'clock', 'text' => '<p>Čekáme na potvrzení od terapeuta. Obvykle to trvá do několika hodin.</p>']),
                $this->detailsBrick('default', 'Detaily rezervace', $this->reservationRows()),
                $this->buttonsBrick('Zobrazit detail rezervace', 'Zrušit rezervaci'),
                $this->brick('email-note', ['text' => '<p>Pokud terapeut rezervaci nepotvrdí, budeme vás kontaktovat s návrhem alternativního termínu.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::ReservationConfirmed => [
                $this->brick('email-greeting', ['text' => '<p>Skvělá zpráva, {{ jmeno }}!</p>']),
                $this->brick('email-callout', ['variant' => 'success', 'icon' => 'circle-check', 'text' => '<p>Rezervace byla potvrzena terapeutem.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Vaše rezervace byla právě potvrzena terapeutem. Těšíme se na vás!</p>']),
                $this->detailsBrick('default', 'Detaily rezervace', $this->reservationRows()),
                $this->buttonsBrick('Spravovat rezervaci', 'Zrušit rezervaci'),
                $this->brick('email-note', ['text' => '<p>Storno zdarma je možné nejpozději 24 hodin před termínem návštěvy. Pozdější zrušení je zpoplatněno dle storno podmínek.</p>']),
                $this->brick('email-callout', ['variant' => 'info', 'icon' => 'bell', 'text' => '<p>Připomínku termínu vám pošleme 48 hodin před návštěvou.</p>']),
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
                $this->buttonsBrick('Potvrdit účast', 'Spravovat rezervaci'),
                $this->brick('email-note', ['text' => '<p>Potvrďte svou účast nejpozději 24 hodin před termínem, jinak bude rezervace automaticky zrušena. Zrušení potvrzeného termínu je zpoplatněno dle storno podmínek.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::ReservationCancelled => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Vaše rezervace byla úspěšně zrušena. Níže naleznete přehled zrušené návštěvy.</p>']),
                $this->detailsBrick('danger', 'Zrušená návštěva', $this->cancelledRows()),
                $this->brick('email-paragraph', ['text' => '<p>Mrzí nás, že jste museli zrušit svou návštěvu. Pokud si budete přát, můžete si kdykoliv zarezervovat nový termín.</p>']),
                $this->singleButtonBrick('Zarezervovat nový termín'),
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
                $this->buttonsBrick('Potvrdit účast', 'Spravovat rezervaci'),
                $this->replyCallout(),
            ],
            EmailTemplateKey::ReservationAutoCancelled => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Vaše rezervace byla automaticky zrušena, protože jste do 24 hodin před termínem nepotvrdili svou účast.</p>']),
                $this->detailsBrick('danger', 'Zrušený termín', $this->cancelledRows()),
                $this->brick('email-paragraph', ['text' => '<p>Mrzí nás, že váš termín propadl. Pokud máte stále zájem o návštěvu, můžete si níže rezervovat nový termín.</p>']),
                $this->singleButtonBrick('Zarezervovat nový termín'),
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
     * @return array{type: string, attrs: array{id: string, config: array<string, mixed>}}
     */
    private function buttonsBrick(string $primary, string $secondary): array
    {
        return $this->brick('email-buttons', [
            'buttons' => [
                ['text' => $primary, 'style' => 'primary', 'link_type' => 'custom', 'url' => '{{ odkaz }}'],
                ['text' => $secondary, 'style' => 'outline', 'link_type' => 'custom', 'url' => '{{ odkaz }}'],
            ],
        ]);
    }

    /**
     * @return array{type: string, attrs: array{id: string, config: array<string, mixed>}}
     */
    private function singleButtonBrick(string $primary): array
    {
        return $this->brick('email-buttons', [
            'buttons' => [
                ['text' => $primary, 'style' => 'primary', 'link_type' => 'custom', 'url' => '{{ odkaz }}'],
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
