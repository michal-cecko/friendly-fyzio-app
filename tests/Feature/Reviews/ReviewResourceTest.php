<?php

namespace Tests\Feature\Reviews;

use App\Filament\Clusters\Obsah\Resources\Reviews\Pages\CreateReview;
use App\Filament\Clusters\Obsah\Resources\Reviews\Pages\EditReview;
use App\Filament\Clusters\Obsah\Resources\Reviews\Pages\ListReviews;
use App\Filament\Clusters\Provoz\Resources\Services\ServiceResource;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_view_reviews_list(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ListReviews::class)->assertOk();
    }

    public function test_admin_can_create_review(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateReview::class)
            ->fillForm([
                'rating' => 5,
                'author_name' => 'Jana Nováková',
                'content' => 'Moc mi to pomohlo, děkuji!',
                'visible' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('reviews', [
            'author_name' => 'Jana Nováková',
            'rating' => 5,
            'visible' => true,
        ]);
    }

    public function test_admin_can_link_review_to_customer(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Petr Klient']);

        $this->actingAs($this->admin());

        Livewire::test(CreateReview::class)
            ->fillForm([
                'rating' => 4,
                'client_id' => $customer->getKey(),
                'author_name' => 'Petr Klient',
                'content' => 'Skvělá péče.',
                'visible' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('reviews', [
            'client_id' => $customer->getKey(),
            'author_name' => 'Petr Klient',
        ]);
    }

    public function test_create_requires_author_and_content(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateReview::class)
            ->fillForm([
                'author_name' => null,
                'content' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'author_name' => 'required',
                'content' => 'required',
            ]);
    }

    public function test_admin_can_hide_a_review(): void
    {
        $review = Review::factory()->create(['visible' => true]);

        $this->actingAs($this->admin());

        Livewire::test(EditReview::class, ['record' => $review->getKey()])
            ->fillForm(['visible' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('reviews', [
            'id' => $review->getKey(),
            'visible' => false,
        ]);
    }

    public function test_reviewable_column_shows_the_target_name_and_links_to_it(): void
    {
        $service = Service::factory()->create(['name' => 'Vstupní fyzioterapie']);
        $review = Review::factory()->reviewing($service)->create();

        $this->actingAs($this->admin());

        Livewire::test(ListReviews::class)
            ->assertTableColumnStateSet('reviewable', 'Vstupní fyzioterapie', $review)
            ->assertTableColumnExists(
                'reviewable',
                fn (TextColumn $column): bool => $column->getUrl() === ServiceResource::getUrl('view', ['record' => $service]),
                $review,
            );
    }

    public function test_reviewable_column_is_empty_for_a_general_review(): void
    {
        $review = Review::factory()->create();

        $this->actingAs($this->admin());

        Livewire::test(ListReviews::class)
            ->assertTableColumnStateSet('reviewable', null, $review)
            ->assertTableColumnExists(
                'reviewable',
                fn (TextColumn $column): bool => $column->getUrl() === null
                    && $column->getColor($column->getState()) === null,
                $review,
            );
    }

    public function test_hiding_and_publishing_a_review_is_logged(): void
    {
        $review = Review::factory()->create(['visible' => true]);

        $this->actingAs($this->admin());

        $review->update(['visible' => false]);

        $this->assertDatabaseHas('activity_log', [
            'event' => 'review_hidden',
            'subject_id' => $review->getKey(),
        ]);

        $review->update(['visible' => true]);

        $this->assertDatabaseHas('activity_log', [
            'event' => 'review_published',
            'subject_id' => $review->getKey(),
        ]);

        // The visibility flip is owned by the semantic events, so it must not
        // also file a generic "updated" diff.
        $this->assertDatabaseMissing('activity_log', [
            'event' => 'updated',
            'subject_id' => $review->getKey(),
        ]);
    }
}
