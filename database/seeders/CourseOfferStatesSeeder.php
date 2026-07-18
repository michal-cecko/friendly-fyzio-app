<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseLesson;
use App\Models\CourseSeries;
use App\Models\Room;
use App\Models\User;
use App\Models\Workshop;
use Database\Seeders\Concerns\ImportsMedia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Idempotent demo records covering every public offer state from the designs:
 * an open course, a running (mid-series, pro-rated) one, a full course with a
 * waitlist, a "preparing" course (Inactive series + pre-sale token), a course
 * with no series at all (interest form) and a full workshop with a waitlist.
 * Safe to re-run — everything is keyed by slug.
 */
class CourseOfferStatesSeeder extends Seeder
{
    use ImportsMedia;

    public function run(): void
    {
        $instructor = User::query()->where('role', UserRole::Therapist)->first()
            ?? User::factory()->therapist()->create();
        $room = Room::query()->first() ?? Room::factory()->create();
        $category = CourseCategory::query()->firstOrCreate(
            ['slug' => 'joga'],
            ['name' => 'Jóga', 'display_order' => 0, 'published_at' => now()],
        );

        $clients = User::query()->where('role', UserRole::Customer)->limit(12)->get();

        // 1) Full course with a queue — the waitlist state.
        $full = $this->course($category, $instructor, 'Hormonální jóga', 'Cvičení zaměřené na podporu hormonů a endokrinního systému. Vhodné pro ženy všech věků.', 'photo-1518459031867-a89b944bffe4');
        $fullSeries = $this->series($full, 'leden–duben 2026', today()->addWeeks(2), 6, 2200, CourseSeriesStatus::Open, $instructor, $room);

        $clients->take(6)->each(fn (User $client) => $fullSeries->enrollments()->firstOrCreate(
            ['client_id' => $client->getKey()],
            ['status' => CourseEnrollmentStatus::Active, 'payment_status' => PaymentStatus::Paid, 'paid_at' => now()],
        ));

        foreach ([['Klára Malá', 'klara.mala@example.cz'], ['Věra Tichá', 'vera.ticha@example.cz'], ['Iva Novotná', 'iva.novotna@example.cz']] as [$name, $email]) {
            $fullSeries->waitlistEntries()->firstOrCreate(['email' => $email], ['name' => $name, 'phone' => '+420 604 111 222']);
        }

        // 2) Running course — mid-series registration with a pro-rated price.
        $running = $this->course($category, $instructor, 'Somatická jóga', 'Jemné cvičení zaměřené na uvolnění napětí a obnovení pohyblivosti.', 'photo-1758599879462-23a9d4a4bf2c');
        $this->series($running, 'jaro 2026', today()->subWeeks(3), 12, 2400, CourseSeriesStatus::Open, $instructor, $room);

        // 3) Preparing course — Inactive series, notify-me form + pre-sale link.
        $preparing = $this->course($category, $instructor, 'SM systém', 'Stabilizační a mobilizační systém pro správné držení těla.', 'photo-1717500252297-b09508db7ceb');
        $preparingSeries = $this->series($preparing, 'podzim 2026', today()->addMonths(3), 12, 2600, CourseSeriesStatus::Inactive, $instructor, $room);
        $preparingSeries->ensurePresaleToken();

        // 4) Course without any series — pure interest sign-up.
        $this->course($category, $instructor, 'Jin jóga', 'Hluboké protahování a uvolnění, držení pozic pro regeneraci tkání.', 'photo-1506126613408-eca07ce68773');

        // 5) Full workshop with a queue.
        $workshop = Workshop::query()->updateOrCreate(
            ['slug' => 'baby-massage-workshop'],
            [
                'instructor_id' => $instructor->getKey(),
                'room_id' => $room->getKey(),
                'name' => 'Baby massage workshop',
                'description' => 'Naučte se techniky masáže pro vaše miminko. Dvoudenní workshop pod vedením zkušené fyzioterapeutky.',
                'featured_image' => $this->media('https://images.unsplash.com/photo-1719942274381-c4c05b0dcf68?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080', 'demo-baby-massage'),
                'workshop_date' => today()->addWeeks(5)->toDateString(),
                'start_time' => '09:00',
                'end_time' => '13:00',
                'capacity' => 4,
                'price' => 3500,
                'published_at' => now(),
            ],
        );

        $clients->skip(6)->take(4)->each(fn (User $client) => $workshop->registrations()->firstOrCreate(
            ['client_id' => $client->getKey()],
            ['status' => BookingStatus::Confirmed, 'payment_status' => PaymentStatus::Paid, 'paid_at' => now()],
        ));

        $workshop->waitlistEntries()->firstOrCreate(
            ['email' => 'monika.svata@example.cz'],
            ['name' => 'Monika Svatá', 'phone' => '+420 605 333 444'],
        );
    }

    protected function course(CourseCategory $category, User $instructor, string $name, string $description, string $unsplashId): Course
    {
        return Course::query()->updateOrCreate(
            ['slug' => str($name)->slug()->toString()],
            [
                'category_id' => $category->getKey(),
                'instructor_id' => $instructor->getKey(),
                'name' => $name,
                'description' => $description,
                'featured_image' => $this->media("https://images.unsplash.com/{$unsplashId}?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080", 'demo-'.str($name)->slug()),
                'published_at' => now(),
            ],
        );
    }

    protected function series(Course $course, string $name, Carbon $start, int $capacity, int $price, CourseSeriesStatus $status, User $instructor, Room $room): CourseSeries
    {
        $series = CourseSeries::query()->updateOrCreate(
            ['course_id' => $course->getKey(), 'name' => $course->name.' – '.$name],
            [
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addWeeks(11)->toDateString(),
                'capacity' => $capacity,
                'price' => $price,
                'status' => $status,
            ],
        );

        if ($series->lessons()->count() === 0) {
            foreach (range(0, 11) as $week) {
                CourseLesson::factory()->for($series, 'series')->create([
                    'instructor_id' => $instructor->getKey(),
                    'room_id' => $room->getKey(),
                    'lesson_date' => $start->copy()->addWeeks($week)->toDateString(),
                    'start_time' => '09:00',
                    'end_time' => '10:00',
                ]);
            }
        }

        return $series;
    }
}
