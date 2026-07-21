<?php

namespace Tests\Feature\Reviews;

use App\Livewire\ReviewForm;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\OneOffEvent;
use App\Models\ReviewRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewFormTest extends TestCase
{
    use RefreshDatabase;

    private function requestFor(OneOffEvent $event, User $user): ReviewRequest
    {
        return ReviewRequest::factory()->create([
            'user_id' => $user->getKey(),
            'reviewable_type' => $event->getMorphClass(),
            'reviewable_id' => $event->getKey(),
        ]);
    }

    public function test_submitting_creates_hidden_review_and_completes_request(): void
    {
        $user = User::factory()->customer()->create(['name' => 'Jana Nováková']);
        $event = OneOffEvent::factory()->create(['name' => 'Zdravá záda']);
        $request = $this->requestFor($event, $user);

        Livewire::test(ReviewForm::class, ['token' => $request->token])
            ->assertSet('state', 'form')
            ->set('rating', 5)
            ->set('content', 'Moc mi to pomohlo!')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('state', 'done');

        $this->assertDatabaseHas('reviews', [
            'client_id' => $user->getKey(),
            'reviewable_type' => 'one_off_event',
            'reviewable_id' => $event->getKey(),
            'rating' => 5,
            'author_name' => 'Jana Nováková',
            'content' => 'Moc mi to pomohlo!',
            'visible' => false,
        ]);

        $request->refresh();
        $this->assertNotNull($request->completed_at);
        $this->assertNotNull($request->review_id);
    }

    public function test_rating_is_required(): void
    {
        $user = User::factory()->customer()->create();
        $request = $this->requestFor(OneOffEvent::factory()->create(), $user);

        Livewire::test(ReviewForm::class, ['token' => $request->token])
            ->set('content', 'Bez hvězdiček')
            ->call('submit')
            ->assertHasErrors(['rating' => 'required']);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_invalid_token_shows_invalid_state(): void
    {
        Livewire::test(ReviewForm::class, ['token' => 'neexistujici-token'])
            ->assertSet('state', 'invalid');
    }

    public function test_already_completed_request_cannot_be_reused(): void
    {
        $user = User::factory()->customer()->create();
        $event = OneOffEvent::factory()->create();
        $request = ReviewRequest::factory()->completed()->create([
            'user_id' => $user->getKey(),
            'reviewable_type' => $event->getMorphClass(),
            'reviewable_id' => $event->getKey(),
        ]);

        Livewire::test(ReviewForm::class, ['token' => $request->token])
            ->assertSet('state', 'already')
            ->set('rating', 5)
            ->call('submit')
            ->assertSet('state', 'already');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_course_series_review_attaches_to_the_parent_course(): void
    {
        $user = User::factory()->customer()->create();
        $course = Course::factory()->create();
        $series = CourseSeries::factory()->create(['course_id' => $course->getKey()]);
        $request = ReviewRequest::factory()->create([
            'user_id' => $user->getKey(),
            'reviewable_type' => $series->getMorphClass(),
            'reviewable_id' => $series->getKey(),
        ]);

        Livewire::test(ReviewForm::class, ['token' => $request->token])
            ->set('rating', 4)
            ->call('submit')
            ->assertSet('state', 'done');

        // Review attaches to the Course template, not the CourseSeries instance.
        $this->assertDatabaseHas('reviews', [
            'reviewable_type' => 'course',
            'reviewable_id' => $course->getKey(),
            'rating' => 4,
        ]);
    }

    public function test_course_linked_event_review_attaches_to_the_parent_course(): void
    {
        $user = User::factory()->customer()->create();
        $course = Course::factory()->create();
        $event = OneOffEvent::factory()->withCourse($course)->create();
        $request = $this->requestFor($event, $user);

        Livewire::test(ReviewForm::class, ['token' => $request->token])
            ->set('rating', 5)
            ->call('submit')
            ->assertSet('state', 'done');

        // Course-derived events collect reviews on the course programme.
        $this->assertDatabaseHas('reviews', [
            'reviewable_type' => 'course',
            'reviewable_id' => $course->getKey(),
            'rating' => 5,
        ]);
        $this->assertDatabaseMissing('reviews', [
            'reviewable_type' => 'one_off_event',
            'reviewable_id' => $event->getKey(),
        ]);
    }
}
