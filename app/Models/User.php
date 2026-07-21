<?php

namespace App\Models;

use App\Contracts\Emailable;
use App\Enums\EmailTemplateKey;
use App\Enums\UserRole;
use App\Models\Concerns\Auditable;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Notifications\ClientAccountCreatedNotification;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

class User extends Authenticatable implements Emailable, FilamentUser, HasPasskeys, MustVerifyEmail
{
    use Auditable, HasFactory, HasRoles, HasTags, HasUuids, InteractsWithPasskeys, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'title_before',
        'title_after',
        'email',
        'phone',
        'password',
        'role',
        'acts_as_therapist',
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
            'acts_as_therapist' => 'boolean',
        ];
    }

    /**
     * Display name including academic titles: "Bc. Petra Novotná, DiS.".
     * `name` itself stays clean so first-name extraction (greetings) works.
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(function (): string {
            $name = trim(($this->title_before ? $this->title_before.' ' : '').(string) $this->name);

            return $this->title_after ? "{$name}, {$this->title_after}" : $name;
        });
    }

    public function emailRecipientAddress(): ?string
    {
        return $this->email;
    }

    public function emailRecipientName(): ?string
    {
        return $this->full_name;
    }

    /**
     * Users have no lifecycle mail worth resending except the "account created" welcome
     * (which points to the login/forgotten-password flow, so it carries no one-time
     * token). Verification & password-reset are framework flows and are intentionally
     * excluded; custom compose is the primary path here.
     *
     * @return array<string, array<string, string>>
     */
    public function emailTemplateGroups(): array
    {
        return [
            'Účet' => [
                EmailTemplateKey::AccountCreated->value => EmailTemplateKey::AccountCreated->label(),
            ],
        ];
    }

    public function sendTemplateEmail(EmailTemplateKey $key): void
    {
        match ($key) {
            EmailTemplateKey::AccountCreated => $this->notify(new ClientAccountCreatedNotification),
            default => null,
        };
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

        // An administrator who opts in as a practising therapist gets a therapist
        // profile automatically (unpublished): the calendar, working hours, and
        // booking flows all key off the profile's existence.
        static::saved(function (self $user): void {
            if ($user->role === UserRole::Admin && $user->acts_as_therapist && $user->staffProfile()->doesntExist()) {
                $user->staffProfile()->create([]);
            }
        });
    }

    /**
     * Send the password-reset link using the dashboard-editable CMS template.
     * Every reset path (public "zapomenuté heslo" page, admin reset action)
     * goes through the broker, which calls this method; the notification
     * builds the public password.reset URL itself.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Send the e-mail-verification link using the dashboard-editable CMS
     * template (Laravel's trait hardcodes its own notification class).
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
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
     * Whether this user works as a therapist: either by account type, or as an
     * administrator who opted in via the "acts as therapist" flag.
     */
    public function isTherapist(): bool
    {
        return $this->role === UserRole::Therapist
            || ($this->role === UserRole::Admin && $this->acts_as_therapist);
    }

    /**
     * Users who work as therapists (see isTherapist()).
     */
    public function scopeTherapists(Builder $query): Builder
    {
        return $query->where(fn (Builder $q): Builder => $q
            ->where('role', UserRole::Therapist)
            ->orWhere(fn (Builder $q): Builder => $q
                ->where('role', UserRole::Admin)
                ->where('acts_as_therapist', true)));
    }

    /**
     * Whether the account has been fully deactivated (e.g. a late-cancel refusal to
     * pay the storno fee, or a staff member who has left). Deactivated customers can
     * neither sign in nor book online, and deactivated staff lose panel access.
     */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * Filament is staff-only: administrators and therapists. Customers have no
     * panel access at all — their home is the public client zone (/muj-ucet).
     * The account type also drives the matching Shield role (see booted()).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->isStaff() && ! $this->isDeactivated();
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

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'client_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'client_id');
    }

    public function oneOffEventBookings(): HasMany
    {
        return $this->hasMany(OneOffEventBooking::class, 'client_id');
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
        return $this->hasManyThrough(Reservation::class, StaffProfile::class, 'user_id', 'therapist_id');
    }

    /**
     * Course lessons this user teaches as instructor.
     */
    public function instructedLessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class, 'instructor_id');
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
