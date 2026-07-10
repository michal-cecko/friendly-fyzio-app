<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Tags\HasTags;

class User extends Authenticatable implements FilamentUser, HasPasskeys, MustVerifyEmail
{
    use HasFactory, HasRoles, HasTags, HasUuids, InteractsWithPasskeys, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'newsletter_opted_in_at',
        'deactivated_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'newsletter_opted_in_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    protected static function booted(): void
    {
        // The account type (UserRole) is the single source of truth for a user's
        // panel role: keep the matching Shield/Spatie role in sync on every save.
        static::saved(function (self $user): void {
            $roleName = $user->role?->shieldRole();

            if ($roleName) {
                Role::findOrCreate($roleName);
                $user->syncRoles([$roleName]);
            } else {
                $user->syncRoles([]);
            }
        });
    }

    /**
     * Staff accounts (administrators and therapists) belong in the admin panel;
     * customers belong in the client zone.
     */
    public function isStaff(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Therapist], true);
    }

    /**
     * Whether the account has been fully deactivated (e.g. a late-cancel refusal to
     * pay the storno fee). Deactivated customers can neither sign in nor book online.
     */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * Panel access by account type:
     * - admin panel: staff only (administrators and therapists).
     * - client panel: any authenticated, non-deactivated user; staff who land here are
     *   redirected to the admin panel (see App\Http\Middleware\RedirectStaffToAdmin),
     *   but allowing them keeps the single shared login working.
     * The account type also drives the matching Shield role (see booted()).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->isStaff(),
            'client' => ! $this->isDeactivated() && in_array($this->role, [UserRole::Admin, UserRole::Therapist, UserRole::Customer], true),
            default => false,
        };
    }

    /**
     * Whether this user may impersonate other users in the panel.
     * Gated by the Shield "Impersonate:User" permission (super admins bypass via Gate::before).
     */
    public function canImpersonate(): bool
    {
        return $this->can('Impersonate:User');
    }

    public function clientProfile(): HasOne
    {
        return $this->hasOne(ClientProfile::class);
    }

    public function therapistProfile(): HasOne
    {
        return $this->hasOne(TherapistProfile::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'client_id');
    }

    /**
     * Ad-hoc therapy notes written about this client (see ClientNote).
     */
    public function clientNotes(): HasMany
    {
        return $this->hasMany(ClientNote::class, 'client_id');
    }

    /**
     * Reservations where this user is the therapist (through their therapist profile).
     */
    public function therapistReservations(): HasManyThrough
    {
        return $this->hasManyThrough(Reservation::class, TherapistProfile::class, 'user_id', 'therapist_id');
    }

    /**
     * Course lessons this user teaches as instructor.
     */
    public function instructedLessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class, 'instructor_id');
    }

    public function therapyRecords(): HasMany
    {
        return $this->hasMany(TherapyRecord::class, 'client_id');
    }

    public function courseEnrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class, 'client_id');
    }

    public function creditAccount(): HasOne
    {
        return $this->hasOne(CreditAccount::class, 'client_id');
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class, 'client_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'client_id');
    }

    public function substituteTokens(): HasMany
    {
        return $this->hasMany(SubstituteToken::class, 'client_id');
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class, 'client_id');
    }
}
