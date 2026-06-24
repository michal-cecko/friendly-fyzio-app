<?php

namespace Database\Seeders;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\DayOfWeek;
use App\Enums\PaymentStatus;
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
use App\Models\TherapistWeeklySchedule;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
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
        $serviceNames = [
            'Vstupní vyšetření', 'Klasická masáž', 'Lymfatická drenáž', 'Léčebná tělesná výchova',
            'Suché jehličkování', 'Kineziotaping', 'Mobilizace páteře', 'Sportovní masáž',
        ];

        $services = collect($serviceNames)->map(function (string $name) use ($categories, $rooms) {
            $service = Service::factory()->create([
                'category_id' => $categories->random()->getKey(),
                'name' => $name,
                'slug' => Str::slug($name),
            ]);

            $service->rooms()->sync($rooms->random(fake()->numberBetween(1, 3))->pluck('id')->all());
            CancellationRule::factory()->for($service)->create();

            return $service;
        });

        // --- Therapists: user + profile + weekly schedules + service links ---
        $therapists = collect();
        foreach (range(1, 4) as $ignored) {
            $user = User::factory()->therapist()->create(['name' => fake('cs_CZ')->name()]);
            $therapist = TherapistProfile::factory()->for($user)->create();
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
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        foreach (range(1, 30) as $ignored) {
            $hour = fake()->numberBetween(8, 17);

            Reservation::factory()->create([
                'client_id' => $clients->random()->getKey(),
                'service_id' => $services->random()->getKey(),
                'therapist_id' => $therapists->random()->getKey(),
                'room_id' => $rooms->random()->getKey(),
                'reservation_date' => $weekStart->copy()->addDays(fake()->numberBetween(0, 6))->toDateString(),
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
