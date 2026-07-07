<?php

namespace Tests\Feature\Reviews;

use App\Filament\Clusters\Obsah\Resources\Reviews\Pages\CreateReview;
use App\Filament\Clusters\Obsah\Resources\Reviews\Pages\EditReview;
use App\Filament\Clusters\Obsah\Resources\Reviews\Pages\ListReviews;
use App\Models\Review;
use App\Models\User;
use Filament\Facades\Filament;
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
                'author_role' => 'účastnice kurzu',
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
}
