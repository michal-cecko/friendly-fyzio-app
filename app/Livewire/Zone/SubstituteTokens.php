<?php

namespace App\Livewire\Zone;

use App\Models\Lesson;
use App\Models\SubstituteToken;
use App\Models\User;
use App\Support\Substitutes\RedeemToken;
use App\Support\Substitutes\SubstituteException;
use App\Support\Substitutes\SubstituteOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * "Náhradní vstupy": the client's substitute tokens and the redeem flow —
 * pick a free place in an allowed parallel course (pencil frames
 * Profile/Substitute Tokens + Modal/Use Token).
 */
class SubstituteTokens extends Component
{
    public ?string $redeemingTokenId = null;

    public function openRedeem(string $tokenId): void
    {
        $this->redeemingTokenId = $tokenId;
    }

    public function closeRedeem(): void
    {
        $this->redeemingTokenId = null;
    }

    public function redeem(string $lessonId, RedeemToken $redeemToken): void
    {
        $token = $this->redeemingToken();

        if ($token === null) {
            return;
        }

        $lesson = app(SubstituteOptions::class)->forToken($token)->firstWhere('id', $lessonId);

        if ($lesson === null) {
            $this->addError('redeem', 'Tuto lekci si jako náhradu vybrat nelze.');

            return;
        }

        try {
            $redeemToken($token, $lesson);
        } catch (SubstituteException $exception) {
            $this->addError('redeem', $exception->getMessage());

            return;
        }

        $this->redeemingTokenId = null;

        session()->flash('status', 'Náhradní lekce je rezervována. Potvrzení jsme vám poslali e-mailem.');
    }

    protected function redeemingToken(): ?SubstituteToken
    {
        if (blank($this->redeemingTokenId)) {
            return null;
        }

        return SubstituteToken::query()
            ->whereKey($this->redeemingTokenId)
            ->where('client_id', Auth::id())
            ->whereNull('used_at')
            ->where('expires_at', '>=', now())
            ->with('sourceLesson.series.course')
            ->first();
    }

    /**
     * @return Collection<int, Lesson>
     */
    protected function redeemOptions(): Collection
    {
        $token = $this->redeemingToken();

        return $token === null ? new Collection : app(SubstituteOptions::class)->forToken($token);
    }

    public function render(): View
    {
        return view('livewire.zone.substitute-tokens', [
            'tokens' => $this->user()->substituteTokens()
                ->with(['sourceLesson.series.course', 'usedForLesson.series.course'])
                ->orderByRaw('used_at is not null')
                ->orderBy('expires_at')
                ->get(),
            'redeemingToken' => $this->redeemingToken(),
            'options' => $this->redeemOptions(),
        ]);
    }

    protected function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
