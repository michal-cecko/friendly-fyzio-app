<?php

namespace Database\Seeders;

use App\Enums\ExamType;
use App\Enums\ServiceVisibility;
use App\Models\CancellationRule;
use App\Models\Room;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StaffProfile;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The real service catalogue. Physiotherapy is sold as a pair: a public
 * "Vstupní" examination for new patients and a cheaper "Kontrolní" follow-up
 * restricted to existing clients (gated by login plus the recency window in
 * `existing_client_months`). Massages carry no exam type.
 *
 * Runs after RealDataSeeder — it attaches the real therapists and rooms, and a
 * service with no therapist is not bookable at all ({@see Service::scopeBookable}).
 *
 * Idempotent: services are keyed by slug and their therapists, rooms and
 * cancellation rule are re-synced, so pricing or staffing changes made here
 * land on the next run.
 */
class ServiceSeeder extends Seeder
{
    /**
     * The six physiotherapists, by login e-mail. Lucie is an administrator who
     * also practises, so she is included via `acts_as_therapist`.
     */
    protected const array PHYSIOTHERAPISTS = [
        'lucie.fickerova@friendlyfyzio.cz',
        'renata.prnka@friendlyfyzio.cz',
        'sarka.antosikova@friendlyfyzio.cz',
        'lada.cincilova@friendlyfyzio.cz',
        'ema.murcova@friendlyfyzio.cz',
        'daniela.steblova@friendlyfyzio.cz',
    ];

    protected const string DENISA = 'denisa.novakova@friendlyfyzio.cz';

    protected const string AMANI = 'lucie.amani@friendlyfyzio.cz';

    public function run(): void
    {
        $categories = ServiceCategory::query()->pluck('id', 'slug');

        if ($categories->isEmpty()) {
            $this->command?->warn('No service categories — run ServiceCategorySeeder first.');

            return;
        }

        $profiles = $this->staffProfilesByEmail();
        // Treatments happen in the two consulting rooms; the gyms are for
        // courses and group sessions, which are not services.
        $rooms = Room::query()->whereIn('short_name', ['AV', 'AM'])->pluck('id')->all();
        $cancelBeforeHours = (int) Settings::get('reservation.cancel_before_hours', 24);

        // Šárka is the one physiotherapist who does not list pregnancy care.
        $pregnancyTherapists = array_values(array_diff(self::PHYSIOTHERAPISTS, ['sarka.antosikova@friendlyfyzio.cz']));
        $pelvicFloorTherapists = [...self::PHYSIOTHERAPISTS, self::AMANI];
        // Apparatus therapy is phone-booked only, but any practising therapist
        // may record such a visit, so all of them are attached.
        $everyone = array_keys($profiles);

        foreach ($this->services($pregnancyTherapists, $pelvicFloorTherapists, $everyone) as $definition) {
            $categoryId = $categories[$definition['category']] ?? null;

            if ($categoryId === null) {
                $this->command?->warn("Service category {$definition['category']} is missing — skipping {$definition['name']}.");

                continue;
            }

            $service = Service::query()->updateOrCreate(
                ['slug' => Str::slug($definition['name'])],
                [
                    'category_id' => $categoryId,
                    'name' => $definition['name'],
                    'exam_type' => $definition['exam_type'] ?? null,
                    'duration_minutes' => $definition['duration'],
                    'price' => $definition['price'],
                    'visibility' => $definition['visibility'] ?? ServiceVisibility::Public,
                    'existing_client_months' => $definition['existing_client_months'] ?? null,
                    'published_at' => now(),
                ],
            );

            $service->rooms()->sync($rooms);
            $service->therapists()->sync($this->profileIds($profiles, $definition['therapists']));

            CancellationRule::query()->updateOrCreate(
                ['service_id' => $service->getKey()],
                ['cancel_before_hours' => $cancelBeforeHours],
            );
        }
    }

    /**
     * Practising therapists only, keyed by e-mail. Staff who have left are
     * excluded, and so is Adéla: she holds a profile for the team page and may
     * record visits as an administrator, but she does not treat clients.
     *
     * @return array<string, string> e-mail => therapist profile id
     */
    protected function staffProfilesByEmail(): array
    {
        return StaffProfile::query()
            ->whereHas('user', fn ($query) => $query->therapists()->whereNull('deactivated_at'))
            ->with('user:id,email')
            ->get()
            ->mapWithKeys(fn (StaffProfile $profile): array => [$profile->user->email => $profile->getKey()])
            ->all();
    }

    /**
     * @param  array<string, string>  $profiles
     * @param  list<string>  $emails
     * @return list<string>
     */
    protected function profileIds(array $profiles, array $emails): array
    {
        return array_values(array_filter(array_map(
            fn (string $email): ?string => $profiles[$email] ?? null,
            $emails,
        )));
    }

    /**
     * @param  list<string>  $pregnancyTherapists
     * @param  list<string>  $pelvicFloorTherapists
     * @param  list<string>  $everyone
     * @return list<array<string, mixed>>
     */
    protected function services(array $pregnancyTherapists, array $pelvicFloorTherapists, array $everyone): array
    {
        return [
            [
                'category' => 'fyzioterapie',
                'name' => 'Vstupní vyšetření pohybového aparátu',
                'exam_type' => ExamType::Vstupni,
                'duration' => 90,
                'price' => 1200,
                'visibility' => ServiceVisibility::Public,
                'therapists' => self::PHYSIOTHERAPISTS,
            ],
            [
                'category' => 'fyzioterapie',
                'name' => 'Kontrolní terapie pohybového aparátu',
                'exam_type' => ExamType::Kontrolni,
                'duration' => 60,
                'price' => 800,
                'visibility' => ServiceVisibility::Clients,
                'existing_client_months' => 12,
                'therapists' => self::PHYSIOTHERAPISTS,
            ],
            [
                'category' => 'fyzioterapie',
                'name' => 'Terapie pánevního dna',
                'exam_type' => ExamType::Vstupni,
                'duration' => 90,
                'price' => 1300,
                'visibility' => ServiceVisibility::Public,
                'therapists' => $pelvicFloorTherapists,
            ],
            [
                'category' => 'fyzioterapie',
                'name' => 'Kontrolní terapie pánevního dna',
                'exam_type' => ExamType::Kontrolni,
                'duration' => 60,
                'price' => 850,
                'visibility' => ServiceVisibility::Clients,
                'existing_client_months' => 12,
                'therapists' => $pelvicFloorTherapists,
            ],
            [
                'category' => 'fyzioterapie',
                'name' => 'Těhotenská fyzioterapie',
                'exam_type' => ExamType::Vstupni,
                'duration' => 90,
                'price' => 1300,
                'visibility' => ServiceVisibility::Public,
                'therapists' => $pregnancyTherapists,
            ],
            [
                'category' => 'fyzioterapie',
                'name' => 'Kontrolní těhotenská fyzioterapie',
                'exam_type' => ExamType::Kontrolni,
                'duration' => 60,
                'price' => 850,
                'visibility' => ServiceVisibility::Clients,
                'existing_client_months' => 12,
                'therapists' => $pregnancyTherapists,
            ],
            [
                'category' => 'relaxace',
                'name' => 'Klasická masáž',
                'duration' => 60,
                'price' => 900,
                'therapists' => [self::DENISA],
            ],
            [
                'category' => 'relaxace',
                'name' => 'Lymfatické masáže',
                'duration' => 90,
                'price' => 1100,
                'therapists' => [self::DENISA],
            ],
            [
                'category' => 'relaxace',
                'name' => 'Těhotenské masáže',
                'duration' => 60,
                'price' => 1000,
                'therapists' => [self::DENISA],
            ],
            [
                'category' => 'relaxace',
                'name' => 'Masáže miminek a dětí',
                'duration' => 30,
                'price' => 500,
                'therapists' => [self::DENISA],
            ],
            [
                'category' => 'relaxace',
                'name' => 'Bylinná napářka',
                'duration' => 60,
                'price' => 1200,
                'visibility' => ServiceVisibility::Hidden,
                'therapists' => [self::DENISA, self::AMANI],
            ],
            // Přístrojová terapie is booked over the phone, so it stays out of
            // the public wizard — staff can still schedule it in the calendar.
            [
                'category' => 'pristrojova-terapie',
                'name' => 'Laserová terapie',
                'duration' => 30,
                'price' => 500,
                'visibility' => ServiceVisibility::Hidden,
                'therapists' => $everyone,
            ],
            [
                'category' => 'pristrojova-terapie',
                'name' => 'Kryoterapie',
                'duration' => 15,
                'price' => 490,
                'visibility' => ServiceVisibility::Hidden,
                'therapists' => $everyone,
            ],
        ];
    }
}
