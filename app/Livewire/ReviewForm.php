<?php

namespace App\Livewire;

use App\Models\Review;
use App\Models\ReviewRequest;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Public review form reached only via a magic-link token (see ReviewRequest).
 * Shows the recipient what they're reviewing, collects a star rating and an
 * optional description, and stores a hidden Review pending staff approval.
 */
class ReviewForm extends Component
{
    #[Locked]
    public string $token = '';

    public ?int $rating = null;

    public string $content = '';

    /** form | done | invalid | already */
    public string $state = 'form';

    public string $targetLabel = '';

    public function mount(string $token): void
    {
        $this->token = $token;

        $request = $this->request();

        if ($request === null) {
            $this->state = 'invalid';

            return;
        }

        if ($request->isCompleted()) {
            $this->state = 'already';

            return;
        }

        $this->targetLabel = $request->targetLabel();
    }

    public function submit(): void
    {
        $request = $this->request();

        if ($request === null) {
            $this->state = 'invalid';

            return;
        }

        if ($request->isCompleted()) {
            $this->state = 'already';

            return;
        }

        $data = $this->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'content' => ['nullable', 'string', 'max:2000'],
        ], [
            'rating.required' => 'Vyberte prosím počet hvězdiček.',
            'rating.between' => 'Hodnocení musí být 1 až 5 hvězdiček.',
        ], [
            'content' => 'text recenze',
        ]);

        $target = $request->reviewTarget();

        $review = Review::create([
            'client_id' => $request->user_id,
            'reviewable_type' => $target?->getMorphClass(),
            'reviewable_id' => $target?->getKey(),
            'rating' => $data['rating'],
            'content' => $data['content'] ?? '',
            'author_name' => $request->user?->name ?? '',
            'visible' => false,
        ]);

        $request->forceFill([
            'completed_at' => now(),
            'review_id' => $review->getKey(),
        ])->save();

        $this->state = 'done';
    }

    protected function request(): ?ReviewRequest
    {
        return ReviewRequest::query()
            ->with(['user', 'reviewable'])
            ->where('token', $this->token)
            ->first();
    }

    public function render(): View
    {
        return view('livewire.review-form');
    }
}
