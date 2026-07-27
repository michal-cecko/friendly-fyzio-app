<?php

namespace App\Livewire\Zone;

use App\Models\User;
use App\Notifications\Auth\VerifyEmailChangeForClientNotification;
use App\Support\ActivityLog\LogActivity;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/**
 * "Můj profil": three independently saved sections — personal details, password
 * change and company billing details (pencil frame Profile/My Profile).
 * Changing the e-mail re-triggers verification: the new address has to prove
 * itself before the zone opens again.
 */
class Profile extends Component
{
    public string $name = '';

    public string $title_before = '';

    public string $title_after = '';

    public string $email = '';

    public string $phone = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $billing_name = '';

    public string $company_ico = '';

    public string $company_dic = '';

    public string $billing_address = '';

    public function mount(): void
    {
        $user = $this->user();
        $profile = $user->clientProfile;

        $this->name = (string) $user->name;
        $this->title_before = (string) ($user->title_before ?? '');
        $this->title_after = (string) ($user->title_after ?? '');
        $this->email = (string) $user->email;
        $this->phone = (string) ($user->phone ?? '');

        $this->billing_name = (string) ($profile?->billing_name ?? '');
        $this->company_ico = (string) ($profile?->company_ico ?? '');
        $this->company_dic = (string) ($profile?->company_dic ?? '');
        $this->billing_address = (string) ($profile?->billing_address ?? '');
    }

    public function saveDetails()
    {
        $user = $this->user();

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'title_before' => ['nullable', 'string', 'max:255'],
            'title_after' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
            'phone' => ['required', 'string', 'max:255'],
        ], [], ['name' => 'jméno', 'title_before' => 'titul před jménem', 'title_after' => 'titul za jménem', 'email' => 'e-mail', 'phone' => 'telefon']);

        $emailChanged = $user->email !== $this->email;

        $user->fill([
            'name' => $this->name,
            'title_before' => $this->title_before ?: null,
            'title_after' => $this->title_after ?: null,
            'email' => $this->email,
            'phone' => $this->phone,
        ]);

        if ($emailChanged) {
            // The new address has to prove itself before the zone reopens.
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            // Re-verify the new address with e-mail-change wording, not the
            // registration copy that sendEmailVerificationNotification() sends.
            $user->notify(new VerifyEmailChangeForClientNotification);

            return redirect()->route('verification.notice');
        }

        session()->flash('details-status', 'Osobní údaje jsme uložili.');

        return null;
    }

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], ['current_password' => 'současné heslo', 'password' => 'nové heslo']);

        $user = $this->user();
        $user->update(['password' => $this->password]);

        // The password itself is never audited, so a password-only save leaves
        // no attribute diff — record the change as a semantic event instead.
        LogActivity::record('password_changed', $user, 'Heslo změněno');

        $this->reset('current_password', 'password', 'password_confirmation');

        session()->flash('password-status', 'Heslo bylo změněno.');
    }

    public function saveBilling(): void
    {
        $this->validate([
            'billing_name' => ['nullable', 'string', 'max:255'],
            'company_ico' => ['nullable', 'string', 'max:20'],
            'company_dic' => ['nullable', 'string', 'max:20'],
            'billing_address' => ['nullable', 'string', 'max:500'],
        ]);

        $this->user()->clientProfile()->updateOrCreate([], [
            'billing_name' => $this->billing_name ?: null,
            'company_ico' => $this->company_ico ?: null,
            'company_dic' => $this->company_dic ?: null,
            'billing_address' => $this->billing_address ?: null,
        ]);

        session()->flash('billing-status', 'Fakturační údaje jsme uložili.');
    }

    protected function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        return view('livewire.zone.profile');
    }
}
