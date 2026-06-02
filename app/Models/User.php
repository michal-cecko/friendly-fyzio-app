<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Tags\HasTags;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, HasRoles, HasTags, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'newsletter_opted_in_at',
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

            if (! $roleName) {
                $user->syncRoles([]);

                return;
            }

            Role::findOrCreate($roleName);
            $user->syncRoles([$roleName]);
        });
    }

    /**
     * Only staff (administrators and therapists) may access the Filament admin panel.
     * The account type also drives the matching Shield role (see booted()).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Therapist], true);
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
