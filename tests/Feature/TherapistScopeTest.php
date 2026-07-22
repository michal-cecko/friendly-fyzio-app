<?php

namespace Tests\Feature;

use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Clusters\System\Resources\Users\UserResource;
use App\Models\Course;
use App\Models\Reservation;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TherapistScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: StaffProfile}
     */
    private function therapistWithProfile(): array
    {
        // The Therapist capability auto-creates the staff profile.
        $user = User::factory()->therapist()->create();

        return [$user, $user->staffProfile];
    }

    public function test_a_therapist_only_sees_their_own_reservations(): void
    {
        [$therapistA, $profileA] = $this->therapistWithProfile();
        [, $profileB] = $this->therapistWithProfile();

        $mine = Reservation::factory()->create(['therapist_id' => $profileA->id]);
        $theirs = Reservation::factory()->create(['therapist_id' => $profileB->id]);

        $this->actingAs($therapistA);

        $ids = ReservationResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    public function test_a_therapist_only_sees_clients_they_have_treated(): void
    {
        [$therapistA, $profileA] = $this->therapistWithProfile();
        [, $profileB] = $this->therapistWithProfile();

        $treated = User::factory()->customer()->create();
        $stranger = User::factory()->customer()->create();

        Reservation::factory()->create(['client_id' => $treated->id, 'therapist_id' => $profileA->id]);
        Reservation::factory()->create(['client_id' => $stranger->id, 'therapist_id' => $profileB->id]);

        $this->actingAs($therapistA);

        $ids = ClientResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($treated->id));
        $this->assertFalse($ids->contains($stranger->id));
    }

    public function test_a_therapist_only_sees_courses_they_instruct(): void
    {
        [$therapistA] = $this->therapistWithProfile();
        [$therapistB] = $this->therapistWithProfile();

        $mine = Course::factory()->create(['instructor_id' => $therapistA->id]);
        $theirs = Course::factory()->create(['instructor_id' => $therapistB->id]);

        $this->actingAs($therapistA);

        $ids = CourseResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    public function test_an_admin_sees_every_reservation_and_client(): void
    {
        $admin = User::factory()->admin()->create();
        [, $profileA] = $this->therapistWithProfile();
        [, $profileB] = $this->therapistWithProfile();

        Reservation::factory()->create(['therapist_id' => $profileA->id]);
        Reservation::factory()->create(['therapist_id' => $profileB->id]);

        $this->actingAs($admin);

        $this->assertSame(2, ReservationResource::getEloquentQuery()->count());
    }

    public function test_an_admin_acting_as_therapist_is_not_scoped(): void
    {
        $admin = User::factory()->admin()->therapist()->create();
        [, $profileOther] = $this->therapistWithProfile();

        Reservation::factory()->create(['therapist_id' => $profileOther->id]);

        $this->actingAs($admin);

        // Admins keep the full view even when they also practise.
        $this->assertSame(1, ReservationResource::getEloquentQuery()->count());
    }

    public function test_therapists_cannot_reach_the_staff_users_resource(): void
    {
        [$therapist] = $this->therapistWithProfile();
        $admin = User::factory()->admin()->create();

        $this->actingAs($therapist);
        $this->assertFalse(UserResource::canAccess());

        $this->actingAs($admin);
        $this->assertTrue(UserResource::canAccess());
    }
}
