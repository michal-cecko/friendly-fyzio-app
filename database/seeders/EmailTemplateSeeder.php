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
            EmailTemplateKey::CourseEnrollmentReceived => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-callout', ['variant' => 'success', 'icon' => 'circle-check', 'text' => '<p>Přijali jsme vaši přihlášku na kurz.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Děkujeme za přihlášku — místo v kurzu vám držíme {{ rezervace_hodin }} hodin. Přihlášení dokončíte uhrazením kurzovného QR platbou nebo převodem na uvedený účet.</p>']),
                $this->detailsBrick('default', 'Detail přihlášky', [
                    ['Kurz:', '{{ kurz }}'],
                    ['Série:', '{{ beh }}'],
                    ['Období:', '{{ obdobi }}'],
                    ['Nejbližší lekce:', '{{ rozvrh }}'],
                ]),
                $this->brick('email-payment', ['title' => 'Platební údaje', 'show_qr' => true, 'show_due' => true]),
                $this->brick('email-note', ['text' => '<p>Pokud platbu neobdržíme do {{ rezervace_hodin }} hodin, přihláška se automaticky zruší a místo uvolníme dalším zájemcům.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::WorkshopRegistrationReceived => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-callout', ['variant' => 'success', 'icon' => 'circle-check', 'text' => '<p>Přijali jsme vaši registraci na workshop.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Děkujeme za registraci — místo vám držíme {{ rezervace_hodin }} hodin. Registraci dokončíte uhrazením účastnického poplatku QR platbou nebo převodem na uvedený účet.</p>']),
                $this->detailsBrick('default', 'Detail registrace', [
                    ['Workshop:', '{{ workshop }}'],
                    ['Termín:', '{{ termin }}'],
                    ['Místo:', '{{ misto }}'],
                ]),
                $this->brick('email-payment', ['title' => 'Platební údaje', 'show_qr' => true, 'show_due' => true]),
                $this->brick('email-note', ['text' => '<p>Pokud platbu neobdržíme do {{ rezervace_hodin }} hodin, registrace se automaticky zruší a místo uvolníme dalším zájemcům.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::LessonBookingReceived => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-callout', ['variant' => 'success', 'icon' => 'circle-check', 'text' => '<p>Přijali jsme vaši rezervaci lekce.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Děkujeme za rezervaci — místo na lekci vám držíme {{ rezervace_hodin }} hodin. Rezervaci dokončíte uhrazením ceny lekce QR platbou nebo převodem na uvedený účet.</p>']),
                $this->detailsBrick('default', 'Detail rezervace', [
                    ['Lekce:', '{{ lekce }}'],
                    ['Termín:', '{{ termin }}'],
                    ['Místo:', '{{ misto }}'],
                ]),
                $this->brick('email-payment', ['title' => 'Platební údaje', 'show_qr' => true, 'show_due' => true]),
                $this->brick('email-note', ['text' => '<p>Pokud platbu neobdržíme do {{ rezervace_hodin }} hodin, rezervace se automaticky zruší a místo uvolníme dalším zájemcům.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::EnrollmentAutoCancelled => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-callout', ['variant' => 'warning', 'icon' => 'circle-alert', 'text' => '<p>Vaše přihláška byla automaticky zrušena.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Platbu jsme v rezervační lhůtě neobdrželi, a proto jsme vaše místo uvolnili dalším zájemcům.</p>']),
                $this->detailsBrick('muted', 'Zrušená přihláška', [
                    ['Název:', '{{ nazev }}'],
                    ['Termín:', '{{ termin }}'],
                    ['Důvod:', '{{ duvod }}'],
                ]),
                $this->brick('email-note', ['text' => '<p>Pokud máte o účast stále zájem, přihlaste se prosím znovu — nebo nám odpovězte na tento e-mail a domluvíme se.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::WaitlistJoined => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-callout', ['variant' => 'info', 'icon' => 'clock', 'text' => '<p>Jste na čekací listině (pořadí: {{ poradi }}).</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Kapacita je momentálně plná. Jakmile se uvolní místo, ozveme se vám e-mailem — pořadí určuje čas přihlášení na čekací listinu.</p>']),
                $this->detailsBrick('default', 'Čekací listina', [
                    ['Název:', '{{ nazev }}'],
                    ['Termín:', '{{ termin }}'],
                    ['Vaše pořadí:', '{{ poradi }}'],
                ]),
                $this->brick('email-note', ['text' => '<p>Zařazení na čekací listinu je nezávazné a kdykoli se z ní můžete odhlásit odpovědí na tento e-mail.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::WaitlistSpotAvailable => [
                $this->brick('email-greeting', ['text' => '<p>Dobrá zpráva, {{ jmeno }}!</p>']),
                $this->brick('email-callout', ['variant' => 'success', 'icon' => 'circle-check', 'text' => '<p>Uvolnilo se místo a je vaše.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Z čekací listiny jsme vás přihlásili na uvolněné místo. Přihlášení dokončíte uhrazením platby — místo vám držíme {{ rezervace_hodin }} hodin, poté ho nabídneme dalšímu v pořadí.</p>']),
                $this->detailsBrick('default', 'Detail přihlášky', [
                    ['Název:', '{{ nazev }}'],
                    ['Termín:', '{{ termin }}'],
                ]),
                $this->brick('email-payment', ['title' => 'Platební údaje', 'show_qr' => true, 'show_due' => true]),
                $this->brick('email-note', ['text' => '<p>Pokud už o místo nemáte zájem, nemusíte nic dělat — po uplynutí lhůty se přihláška sama zruší.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::CourseRegistrationOpened => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-callout', ['variant' => 'success', 'icon' => 'bell', 'text' => '<p>Otevřeli jsme přihlašování na kurz, o který jste projevili zájem.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Kurz {{ kurz }} má vypsanou novou sérii {{ beh }} ({{ obdobi }}). Počet míst je omezený, doporučujeme se přihlásit co nejdříve.</p>']),
                $this->brick('email-buttons', [
                    'buttons' => [
                        ['text' => 'Přihlásit se na kurz', 'style' => 'primary', 'link_type' => 'custom', 'url' => '{{ odkaz }}'],
                    ],
                ]),
                $this->brick('email-note', ['text' => '<p>Tento e-mail jste obdrželi, protože jste u kurzu zanechali svůj e-mail s prosbou o upozornění.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::OfferInvitation => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-callout', ['variant' => 'success', 'icon' => 'ticket', 'text' => '<p>Máte přednostní pozvánku.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Rádi bychom vás jako našeho klienta přednostně pozvali. Přes odkaz níže se můžete přihlásit dříve, než termín zveřejníme — místa jsou omezená.</p>']),
                $this->brick('email-note', ['text' => '<p>{{ zprava }}</p>']),
                $this->detailsBrick('default', 'Detail termínu', [
                    ['Název:', '{{ nazev }}'],
                    ['Termín:', '{{ termin }}'],
                ]),
                $this->brick('email-buttons', [
                    'buttons' => [
                        ['text' => 'Rezervovat místo', 'style' => 'primary', 'link_type' => 'custom', 'url' => '{{ odkaz }}'],
                    ],
                ]),
                $this->replyCallout(),
            ],
            EmailTemplateKey::EnrollmentCancelledByClient => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-callout', ['variant' => 'info', 'icon' => 'circle-check', 'text' => '<p>Vaše odhlášení proběhlo.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Na vaši žádost jsme zrušili vaši přihlášku a místo uvolnili dalším zájemcům.</p>']),
                $this->detailsBrick('muted', 'Zrušená přihláška', [
                    ['Název:', '{{ nazev }}'],
                    ['Termín:', '{{ termin }}'],
                ]),
                $this->brick('email-note', ['text' => '<p>Pokud jste přihlášku již uhradili, ozveme se vám ohledně vrácení platby nebo převodu na kredit. Kdybyste si to rozmysleli, stačí se přihlásit znovu.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::EnrollmentCancelledByClinic => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-callout', ['variant' => 'info', 'icon' => 'circle-alert', 'text' => '<p>Vaši přihlášku jsme zrušili.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Rádi bychom vás informovali, že jsme z naší strany zrušili vaši přihlášku. Níže naleznete její přehled.</p>']),
                $this->detailsBrick('muted', 'Zrušená přihláška', [
                    ['Název:', '{{ nazev }}'],
                    ['Termín:', '{{ termin }}'],
                ]),
                $this->brick('email-note', ['text' => '<p>Máte-li k tomu jakékoliv dotazy nebo jste přihlášku již uhradili, ozvěte se nám prosím — společně vše vyřešíme.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::SubstituteTokenGenerated => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-callout', ['variant' => 'success', 'icon' => 'ticket', 'text' => '<p>Za omluvenou lekci máte náhradní vstup.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Omluvili jste se z lekce včas, a proto jsme vám vystavili náhradní vstup. Vyberte si volné místo v souběžné skupině přímo v klientské zóně.</p>']),
                $this->detailsBrick('default', 'Náhradní vstup', [
                    ['Kurz:', '{{ kurz }}'],
                    ['Omluvená lekce:', '{{ lekce }}'],
                    ['Platí do:', '{{ platnost }}'],
                ]),
                $this->brick('email-buttons', [
                    'buttons' => [
                        ['text' => 'Vybrat náhradní lekci', 'style' => 'primary', 'link_type' => 'custom', 'url' => '{{ odkaz }}'],
                    ],
                ]),
                $this->brick('email-note', ['text' => '<p>Po uplynutí platnosti náhradní vstup propadá.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::SubstituteTokenRedeemed => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-callout', ['variant' => 'success', 'icon' => 'circle-check', 'text' => '<p>Náhradní lekce je rezervována.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Uplatnili jste náhradní vstup a máte místo na náhradní lekci. Budeme se na vás těšit.</p>']),
                $this->detailsBrick('default', 'Náhradní lekce', [
                    ['Kurz:', '{{ kurz }}'],
                    ['Termín:', '{{ lekce }}'],
                    ['Místo:', '{{ misto }}'],
                ]),
                $this->brick('email-note', ['text' => '<p>Kdybyste na náhradní lekci nemohli dorazit, dejte nám prosím vědět.</p>']),
                $this->replyCallout(),
            ],
            EmailTemplateKey::LessonScheduleChanged => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-callout', ['variant' => 'warning', 'icon' => 'clock', 'text' => '<p>Došlo ke změně termínu.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Rádi bychom vás informovali o změně termínu v rámci {{ nazev }}. Zkontrolujte prosím nové údaje níže.</p>']),
                $this->detailsBrick('muted', 'Původní termín', [
                    ['Termín:', '{{ puvodni_termin }}'],
                    ['Místo:', '{{ puvodni_misto }}'],
                ]),
                $this->detailsBrick('success', 'Nový termín', [
                    ['Termín:', '{{ termin }}'],
                    ['Místo:', '{{ misto }}'],
                ]),
                $this->replyCallout(),
            ],
            EmailTemplateKey::TherapistEnrollmentCreated => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-callout', ['variant' => 'info', 'icon' => 'user-plus', 'text' => '<p>Máte novou přihlášku od klienta.</p>']),
                $this->detailsBrick('default', 'Detail přihlášky', [
                    ['Název:', '{{ nazev }}'],
                    ['Termín:', '{{ termin }}'],
                    ['Klient:', '{{ klient }}'],
                    ['Tel. klienta:', '{{ telefon_klienta }}'],
                    ['E-mail klienta:', '{{ email_klienta }}'],
                    ['Poznámka:', '{{ poznamka }}'],
                ]),
                $this->brick('email-buttons', [
                    'buttons' => [
                        ['text' => 'Zobrazit v administraci', 'style' => 'primary', 'link_type' => 'custom', 'url' => '{{ odkaz }}'],
                    ],
                ]),
                $this->automatedNote(),
            ],
            EmailTemplateKey::TherapistLessonScheduleChanged => [
                $this->brick('email-greeting', ['text' => '<p>Dobrý den, {{ jmeno }},</p>']),
                $this->brick('email-callout', ['variant' => 'info', 'icon' => 'clock', 'text' => '<p>Změnil se termín lekce, kterou vedete.</p>']),
                $this->brick('email-paragraph', ['text' => '<p>Termín v rámci {{ nazev }} byl změněn. Přihlášení účastníci byli o změně informováni. Níže naleznete přehled.</p>']),
                $this->detailsBrick('muted', 'Původní termín', [
                    ['Termín:', '{{ puvodni_termin }}'],
                    ['Místo:', '{{ puvodni_misto }}'],
                ]),
                $this->detailsBrick('success', 'Nový termín', [
                    ['Termín:', '{{ termin }}'],
                    ['Místo:', '{{ misto }}'],
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
