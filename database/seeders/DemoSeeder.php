<?php

namespace Database\Seeders;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\DayOfWeek;
use App\Enums\ExamType;
use App\Enums\PaymentStatus;
use App\Enums\ServiceVisibility;
use App\Enums\WeekType;
use App\Models\Building;
use App\Models\CancellationRule;
use App\Models\ClientProfile;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseSeries;
use App\Models\LessonAttendance;
use App\Models\OneTimeLesson;
use App\Models\OneTimeLessonBooking;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlocking;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TherapistProfile;
use App\Models\TherapistSpecialization;
use App\Models\TherapistWeeklySchedule;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Database\Seeders\Concerns\ImportsMedia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    use ImportsMedia;

    public function run(): void
    {
        // --- Buildings + rooms ---
        $rooms = collect();
        $layout = [
            'Hlavní budova' => ['Ordinace 1', 'Ordinace 2', 'Masážní místnost', 'Tělocvična'],
            'Pobočka Sever' => ['Ordinace Sever', 'Rehabilitace'],
        ];

        foreach ($layout as $buildingName => $roomNames) {
            $building = Building::factory()->create([
                'name' => $buildingName,
                'address' => fake('cs_CZ')->streetAddress().', '.fake('cs_CZ')->city(),
            ]);

            foreach ($roomNames as $roomName) {
                $rooms->push(Room::factory()->for($building)->create([
                    'name' => $roomName,
                ]));
            }
        }

        // --- Service categories (seeded as real published content by ServiceCategorySeeder) ---
        $categories = ServiceCategory::all();
        abort_if($categories->isEmpty(), 500, 'Run ServiceCategorySeeder before DemoSeeder.');

        // --- Services (+ cancellation rule + room links) ---
        // Physiotherapy carries an exam type: "Vstupní" (new patients, public) is
        // paired with a cheaper "Kontrolní" (existing patients only — gated by login
        // + the existing_client_months recency window). Massages have no exam type.
        $physio = $categories->firstWhere('slug', 'fyzioterapie') ?? $categories->first();
        $massage = $categories->firstWhere('slug', 'relaxace') ?? $categories->first();

        $serviceDefs = [
            ['category' => $physio, 'name' => 'Vstupní vyšetření pohybového aparátu', 'exam_type' => ExamType::Vstupni, 'duration' => 90, 'price' => 1200, 'visibility' => ServiceVisibility::Public],
            ['category' => $physio, 'name' => 'Kontrolní terapie pohybového aparátu', 'exam_type' => ExamType::Kontrolni, 'duration' => 60, 'price' => 800, 'visibility' => ServiceVisibility::Clients, 'existing_client_months' => 12],
            ['category' => $physio, 'name' => 'Terapie pánevního dna', 'exam_type' => ExamType::Vstupni, 'duration' => 90, 'price' => 1300, 'visibility' => ServiceVisibility::Public],
            ['category' => $physio, 'name' => 'Kontrolní terapie pánevního dna', 'exam_type' => ExamType::Kontrolni, 'duration' => 60, 'price' => 850, 'visibility' => ServiceVisibility::Clients, 'existing_client_months' => 12],
            ['category' => $physio, 'name' => 'Těhotenská fyzioterapie', 'exam_type' => ExamType::Vstupni, 'duration' => 90, 'price' => 1300, 'visibility' => ServiceVisibility::Public],
            ['category' => $physio, 'name' => 'Kontrolní těhotenská fyzioterapie', 'exam_type' => ExamType::Kontrolni, 'duration' => 60, 'price' => 850, 'visibility' => ServiceVisibility::Clients, 'existing_client_months' => 12],
            ['category' => $massage, 'name' => 'Klasická masáž', 'duration' => 60, 'price' => 900],
            ['category' => $massage, 'name' => 'Lymfatické masáže', 'duration' => 90, 'price' => 1100],
            ['category' => $massage, 'name' => 'Těhotenské masáže', 'duration' => 60, 'price' => 1000],
            ['category' => $massage, 'name' => 'Masáže miminek a dětí', 'duration' => 30, 'price' => 500],
            ['category' => $massage, 'name' => 'Bylinná napářka', 'duration' => 60, 'price' => 1200, 'visibility' => ServiceVisibility::Hidden],
        ];

        $services = collect($serviceDefs)->map(function (array $def) use ($rooms) {
            $service = Service::factory()->create([
                'category_id' => $def['category']->getKey(),
                'name' => $def['name'],
                'slug' => Str::slug($def['name']),
                'exam_type' => $def['exam_type'] ?? null,
                'duration_minutes' => $def['duration'],
                'price' => $def['price'],
                'visibility' => $def['visibility'] ?? ServiceVisibility::Public,
                'existing_client_months' => $def['existing_client_months'] ?? null,
                'published_at' => now(),
            ]);

            $service->rooms()->sync($rooms->random(fake()->numberBetween(1, 3))->pluck('id')->all());
            CancellationRule::factory()->for($service)->create();

            return $service;
        });

        // --- Therapists: user + published public profile + specializations + schedules + service links ---
        // The last therapist is left unpublished on purpose to demonstrate the
        // non-clickable card in the data-driven team grid on /o-nas.
        $therapistPhoto = fn (string $id, string $ixid, string $name): ?int => $this->media(
            "https://images.unsplash.com/{$id}?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid={$ixid}&ixlib=rb-4.1.0&q=80&w=1080",
            $name,
        );

        $therapistDefs = [
            [
                'name' => 'Mgr. Lucie Fičkerová',
                'email' => 'lucie@friendlyfyzio.cz',
                'phone' => '+420 604 793 255',
                'title' => 'Fyzioterapeutka, zakladatelka FriendlyFyzio',
                'badge' => 'Zakladatelka & hlavní terapeutka',
                'photo' => ['photo-1717500252172-b1840ea64f05', 'M3w4NDM0ODN8MHwxfHJhbmRvbXx8fHx8fHx8fDE3NzUwMzYwOTl8'],
                'specializations' => [
                    ['name' => 'Pánevní dno', 'icon' => 'heart', 'description' => 'Diagnostika a terapie dysfunkcí pánevního dna, inkontinence, prolapsů a bolestí v oblasti pánve.'],
                    ['name' => 'Těhotenství a porod', 'icon' => 'user', 'description' => 'Příprava na porod, cvičení v těhotenství, poporodní rehabilitace a péče o diastázu.'],
                    ['name' => 'Dětská fyzioterapie', 'icon' => 'star', 'description' => 'Psychomotorický vývoj kojenců a batolat, Vojtova metoda, skoliózy a vadné držení těla u dětí.'],
                    ['name' => 'Pohybový aparát', 'icon' => 'zap', 'description' => 'Bolesti zad a kloubů, sportovní zranění, pooperační rehabilitace a prevence.'],
                ],
                'bio' => '<p>Jsem zakladatelka kliniky FriendlyFyzio a fyzioterapeutka s více než 12letou praxí. Specializuji se na terapii pánevního dna, péči o ženy v těhotenství a po porodu a dětskou fyzioterapii.</p><p>Věřím, že každý pacient si zaslouží čas, pozornost a přístup šitý na míru. Proto u nás nikdy nespěcháme a každé vyšetření probíhá v klidném a přátelském prostředí.</p>',
                'education' => [
                    ['degree' => 'Mgr. – Fyzioterapie', 'institution' => 'Ostravská univerzita, Lékařská fakulta', 'period' => '2010 – 2012'],
                    ['degree' => 'Bc. – Fyzioterapie', 'institution' => 'Ostravská univerzita, Lékařská fakulta', 'period' => '2007 – 2010'],
                ],
                'certifications' => [
                    ['name' => 'Terapie pánevního dna – pokročilý kurz', 'institution' => 'Institut rehabilitace, Praha', 'year' => '2023'],
                    ['name' => 'DNS – Dynamická neuromuskulární stabilizace', 'institution' => '2. LF UK, Praha', 'year' => '2021'],
                    ['name' => 'Vojtova metoda – základní kurz', 'institution' => 'Rehabilitační ústav Kladruby', 'year' => '2019'],
                ],
                'published' => true,
            ],
            [
                'name' => 'Mgr. Anna Kovaříková',
                'email' => 'anna@friendlyfyzio.cz',
                'phone' => '+420 604 793 256',
                'title' => 'Fyzioterapeutka',
                'badge' => null,
                'photo' => ['photo-1758691462413-b07dee2933fe', 'M3w4NDM0ODN8MHwxfHJhbmRvbXx8fHx8fHx8fDE3NzUwMzYxMDR8'],
                'specializations' => [
                    ['name' => 'Ortopedická rehabilitace', 'icon' => 'activity', 'description' => 'Rehabilitace po úrazech, operacích a při chronických potížích pohybového aparátu.'],
                    ['name' => 'Sport', 'icon' => 'zap', 'description' => 'Prevence i léčba sportovních zranění a návrat k plné výkonnosti.'],
                ],
                'bio' => '<p>Věnuji se ortopedické rehabilitaci a sportovní fyzioterapii. Pomáhám klientům po úrazech i operacích zpět k plnému pohybu.</p>',
                'education' => [
                    ['degree' => 'Mgr. – Fyzioterapie', 'institution' => 'Ostravská univerzita, Lékařská fakulta', 'period' => '2013 – 2015'],
                ],
                'certifications' => [
                    ['name' => 'Kineziologické tejpování', 'institution' => 'FyzioCentrum Brno', 'year' => '2018'],
                ],
                'published' => true,
            ],
            [
                'name' => 'Bc. Petra Novotná',
                'email' => 'petra@friendlyfyzio.cz',
                'phone' => '+420 604 793 257',
                'title' => 'Fyzioterapeutka, lektorka',
                'badge' => null,
                'photo' => ['photo-1701826510684-feb5d0c251da', 'M3w4NDM0ODN8MHwxfHJhbmRvbXx8fHx8fHx8fDE3NzUwMzYxMDh8'],
                'specializations' => [
                    ['name' => 'Jóga', 'icon' => 'sparkles', 'description' => 'Vedení lekcí jógy zaměřených na správné držení těla a uvolnění.'],
                    ['name' => 'SM systém', 'icon' => 'activity', 'description' => 'Spirální stabilizace páteře pro prevenci i léčbu bolestí zad.'],
                    ['name' => 'Pilates', 'icon' => 'star', 'description' => 'Posílení hlubokého stabilizačního systému a zlepšení koordinace.'],
                ],
                'bio' => '<p>Kromě individuální fyzioterapie vedu pohybové kurzy jógy a Pilates. Zaměřuji se na správné držení těla a prevenci bolesti zad.</p>',
                'education' => [
                    ['degree' => 'Bc. – Fyzioterapie', 'institution' => 'Ostravská univerzita, Lékařská fakulta', 'period' => '2015 – 2018'],
                ],
                'certifications' => [
                    ['name' => 'SM systém – stabilizace a mobilizace páteře', 'institution' => 'Spirální stabilizace, Praha', 'year' => '2020'],
                ],
                'published' => true,
            ],
            [
                'name' => 'Jana Beránková',
                'email' => 'jana@friendlyfyzio.cz',
                'phone' => '+420 604 793 258',
                'title' => 'Masérka',
                'badge' => null,
                'photo' => ['photo-1728497872617-d639cf6f3ee3', 'M3w4NDM0ODN8MHwxfHJhbmRvbXx8fHx8fHx8fDE3NzUwMzYxMTN8'],
                'specializations' => [
                    ['name' => 'Relaxační masáže', 'icon' => 'heart', 'description' => 'Klasické a relaxační masáže pro uvolnění napětí a regeneraci.'],
                    ['name' => 'Lymfodrenáž', 'icon' => 'activity', 'description' => 'Manuální lymfatická drenáž podporující odtok lymfy a detoxikaci.'],
                ],
                'bio' => '<p>Nová kolegyně v našem týmu. Profil právě připravujeme.</p>',
                'education' => [],
                'certifications' => [],
                'published' => false,
            ],
        ];

        $therapists = collect();
        foreach ($therapistDefs as $order => $def) {
            $user = User::factory()->therapist()->create([
                'name' => $def['name'],
                'email' => $def['email'],
                'phone' => $def['phone'],
            ]);

            $therapist = TherapistProfile::factory()->for($user)->create([
                'title' => $def['title'],
                'badge' => $def['badge'],
                'bio' => $def['bio'],
                'photo' => $therapistPhoto($def['photo'][0], $def['photo'][1], 'therapist-'.($order + 1)),
                'education' => $def['education'],
                'certifications' => $def['certifications'],
                'display_order' => $order,
                'published_at' => $def['published'] ? now() : null,
            ]);

            foreach ($def['specializations'] as $specOrder => $spec) {
                TherapistSpecialization::factory()->create([
                    'therapist_id' => $therapist->getKey(),
                    'name' => $spec['name'],
                    'icon' => $spec['icon'],
                    'description' => $spec['description'],
                    'display_order' => $specOrder,
                ]);
            }

            $therapists->push($therapist);

            foreach (fake()->randomElements(DayOfWeek::cases(), fake()->numberBetween(2, 3)) as $day) {
                $hour = fake()->numberBetween(7, 13);

                TherapistWeeklySchedule::factory()->create([
                    'therapist_id' => $therapist->getKey(),
                    'room_id' => $rooms->random()->getKey(),
                    'day_of_week' => $day,
                    'week_type' => WeekType::All,
                    'start_time' => sprintf('%02d:00', $hour),
                    'end_time' => sprintf('%02d:00', $hour + 4),
                ]);
            }

            $services->random(fake()->numberBetween(2, 4))
                ->each(fn (Service $service) => $service->therapists()->syncWithoutDetaching([$therapist->getKey()]));
        }

        // --- Room blockings: recurring cleaning + one-off maintenance ---
        $rooms->random(min(3, $rooms->count()))->each(fn (Room $room) => RoomBlocking::factory()
            ->recurring()
            ->for($room)
            ->create([
                'reason' => 'Pravidelný úklid',
                'day_of_week' => DayOfWeek::Friday,
            ]));

        RoomBlocking::factory()->for($rooms->random())->create([
            'reason' => 'Údržba vzduchotechniky',
            'start_at' => Carbon::now()->addWeek()->setTime(9, 0),
            'end_at' => Carbon::now()->addWeek()->setTime(13, 0),
        ]);

        // --- Clients: user + profile ---
        $clients = collect();
        foreach (range(1, 15) as $ignored) {
            $user = User::factory()->customer()->create(['name' => fake('cs_CZ')->name()]);
            ClientProfile::factory()->for($user)->create();
            $clients->push($user);
        }

        // --- Reservations across the current week (populate the calendar) ---
        // A therapist can hold only one reservation per date + start time, so we
        // skip any tuple already taken (the DB enforces this with a unique index).
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $takenSlots = [];
        foreach (range(1, 30) as $ignored) {
            $therapistId = $therapists->random()->getKey();
            $date = $weekStart->copy()->addDays(fake()->numberBetween(0, 6))->toDateString();
            $hour = fake()->numberBetween(8, 17);

            $slotKey = "{$therapistId}|{$date}|{$hour}";
            if (isset($takenSlots[$slotKey])) {
                continue;
            }
            $takenSlots[$slotKey] = true;

            Reservation::factory()->create([
                'client_id' => $clients->random()->getKey(),
                'service_id' => $services->random()->getKey(),
                'therapist_id' => $therapistId,
                'room_id' => $rooms->random()->getKey(),
                'reservation_date' => $date,
                'start_time' => sprintf('%02d:00', $hour),
                'end_time' => sprintf('%02d:00', $hour + 1),
            ]);
        }

        // Instructors are Users (role therapist) — derive their ids from the therapist profiles above.
        $instructorIds = $therapists->pluck('user_id');

        // --- Course categories ---
        $courseCategories = collect(['Skupinová cvičení', 'Kurzy pro rodiče s dětmi', 'Pohybové kurzy'])
            ->values()
            ->map(fn (string $name, int $index) => CourseCategory::factory()->create([
                'name' => $name,
                'slug' => Str::slug($name),
                'display_order' => $index,
                'published_at' => now(),
            ]));

        // --- Courses → series → lessons → enrollments → attendances ---
        $courseNames = ['Zdravá záda', 'Pilates pro začátečníky', 'Jóga pro pokročilé', 'Cvičení v těhotenství'];

        collect($courseNames)->each(function (string $name) use ($courseCategories, $instructorIds, $rooms, $clients) {
            $course = Course::factory()->create([
                'category_id' => $courseCategories->random()->getKey(),
                'instructor_id' => $instructorIds->random(),
                'name' => $name,
                'slug' => Str::slug($name),
                'published_at' => now(),
            ]);

            foreach (range(1, fake()->numberBetween(1, 2)) as $seriesIndex) {
                $seriesStart = Carbon::now()->startOfWeek()->subWeeks(fake()->numberBetween(0, 2));

                $series = CourseSeries::factory()->for($course)->create([
                    'name' => $name.' – běh '.$seriesIndex,
                    'start_date' => $seriesStart->toDateString(),
                    'end_date' => $seriesStart->copy()->addWeeks(8)->toDateString(),
                    'status' => CourseSeriesStatus::Open,
                ]);

                $hour = fake()->numberBetween(8, 17);

                $lessons = collect(range(0, 5))->map(fn (int $week) => CourseLesson::factory()->for($series, 'series')->create([
                    'instructor_id' => $course->instructor_id,
                    'room_id' => $rooms->random()->getKey(),
                    'lesson_date' => $seriesStart->copy()->addWeeks($week)->toDateString(),
                    'start_time' => sprintf('%02d:00', $hour),
                    'end_time' => sprintf('%02d:00', $hour + 1),
                ]));

                $enrolledClients = $clients->random(fake()->numberBetween(4, 8));

                foreach ($enrolledClients as $client) {
                    $paymentStatus = fake()->randomElement([PaymentStatus::Paid, PaymentStatus::Paid, PaymentStatus::Unpaid]);

                    $enrollment = CourseEnrollment::factory()->for($series, 'series')->create([
                        'client_id' => $client->getKey(),
                        'status' => CourseEnrollmentStatus::Active,
                        'payment_status' => $paymentStatus,
                        'paid_at' => $paymentStatus === PaymentStatus::Paid ? now() : null,
                    ]);

                    foreach ($lessons as $lesson) {
                        LessonAttendance::factory()->create([
                            'enrollment_id' => $enrollment->getKey(),
                            'lesson_id' => $lesson->getKey(),
                            'attended' => $lesson->lesson_date->isPast() ? fake()->boolean(80) : false,
                            'cancelled_at' => null,
                            'token_generated' => false,
                        ]);
                    }
                }
            }
        });

        $coursesForLessons = Course::all();

        // --- One-time lessons + bookings ---
        foreach (range(1, 6) as $ignored) {
            $hour = fake()->numberBetween(8, 18);

            $lesson = OneTimeLesson::factory()->create([
                'course_id' => $coursesForLessons->random()->getKey(),
                'instructor_id' => $instructorIds->random(),
                'room_id' => $rooms->random()->getKey(),
                'lesson_date' => Carbon::now()->addDays(fake()->numberBetween(-7, 30))->toDateString(),
                'start_time' => sprintf('%02d:00', $hour),
                'end_time' => sprintf('%02d:00', $hour + 1),
            ]);

            foreach ($clients->random(fake()->numberBetween(2, 5)) as $client) {
                $paymentStatus = fake()->randomElement([PaymentStatus::Paid, PaymentStatus::Unpaid]);

                OneTimeLessonBooking::factory()->create([
                    'client_id' => $client->getKey(),
                    'lesson_id' => $lesson->getKey(),
                    'status' => 'confirmed',
                    'payment_status' => $paymentStatus,
                    'paid_at' => $paymentStatus === PaymentStatus::Paid ? now() : null,
                ]);
            }
        }

        // --- Workshops + registrations ---
        $workshopNames = ['Workshop zdravých zad', 'Dýchací techniky', 'Cvičení s overballem', 'Mobilita kyčlí', 'Pánevní dno'];

        collect($workshopNames)->each(function (string $name) use ($instructorIds, $rooms, $clients) {
            $hour = fake()->numberBetween(9, 16);

            $workshop = Workshop::factory()->create([
                'instructor_id' => $instructorIds->random(),
                'room_id' => $rooms->random()->getKey(),
                'name' => $name,
                'slug' => Str::slug($name),
                'workshop_date' => Carbon::now()->addDays(fake()->numberBetween(-7, 45))->toDateString(),
                'start_time' => sprintf('%02d:00', $hour),
                'end_time' => sprintf('%02d:00', $hour + 2),
                'published_at' => now(),
            ]);

            foreach ($clients->random(fake()->numberBetween(3, 8)) as $client) {
                $paymentStatus = fake()->randomElement([PaymentStatus::Paid, PaymentStatus::Paid, PaymentStatus::Unpaid]);

                WorkshopRegistration::factory()->create([
                    'client_id' => $client->getKey(),
                    'workshop_id' => $workshop->getKey(),
                    'status' => 'confirmed',
                    'payment_status' => $paymentStatus,
                    'paid_at' => $paymentStatus === PaymentStatus::Paid ? now() : null,
                ]);
            }
        });
    }
}
