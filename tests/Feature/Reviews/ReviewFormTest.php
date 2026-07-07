<?php

namespace Tests\Feature\Reviews;

use App\Livewire\ReviewForm;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\ReviewRequest;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewFormTest extends TestCase
{
    use RefreshDatabase;

    private function requestFor(Workshop $workshop, User $user): ReviewRequest
    {
        return ReviewRequest::factory()->create([
            'user_id' => $user->getKey(),
            'reviewable_type' => $workshop->getMorphClass(),
            'reviewable_id' => $workshop->getKey(),
        ]);
    }

    public function test_submitting_creates_hidden_review_and_completes_request(): void
    {
        $user = User::factory()->customer()->create(['name' => 'Jana Nováková']);
        $workshop = Workshop::factory()->create(['name' => 'Zdravá záda']);
        $request = $this->requestFor($workshop, $user);

        Livewire::test(ReviewForm::class, ['token' => $request->token])
            ->assertSet('state', 'form')
            ->set('rating', 5)
            ->set('content', 'Moc mi to pomohlo!')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('state', 'done');

        $this->assertDatabaseHas('reviews', [
            'client_id' => $user->getKey(),
            'reviewable_type' => 'workshop',
            'reviewable_id' => $workshop->getKey(),
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
        $request = $this->requestFor(Workshop::factory()->create(), $user);

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
        $workshop = Workshop::factory()->create();
        $request = ReviewRequest::factory()->completed()->create([
            'user_id' => $user->getKey(),
            'reviewable_type' => $workshop->getMorphClass(),
            'reviewable_id' => $workshop->getKey(),
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
}
