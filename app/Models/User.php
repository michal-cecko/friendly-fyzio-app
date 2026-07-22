<?php

namespace App\Models;

use App\Contracts\Emailable;
use App\Enums\Capability;
use App\Enums\EmailTemplateKey;
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
use Illuminate\Support\Collection;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Tags\HasTags;

class User extends Authenticatable implements Emailable, FilamentUser, HasPasskeys, MustVerifyEmail
{
    use Auditable, HasFactory, HasRoles, HasTags, HasUuids, InteractsWithPasskeys, Notifiable, SoftDeletes;

    /** The Spatie role marking a client-zone identity (see isCustomer()). */
    public const string CUSTOMER_ROLE = 'customer';

    protected $fillable = [
        'name',
        'title_before',
        'title_after',
        'email',
        'phone',
        'password',
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

    /**
     * Therapists and lecturers need an unpublished profile: the calendar,
     * working hours and instructor pickers all key off its existence. Pure
     * admins/super-admins don't perform clinic work, so they get one only if
     * explicitly placed on the team (e.g. the assistant, seeded her own).
     *
     * Called from the capability mutators rather than a save hook, because
     * capabilities are Spatie roles — granting one doesn't re-save the user.
     */
    protected function ensureStaffProfile(): void
    {
        if (($this->isTherapist() || $this->isLecturer()) && $this->staffProfile()->doesntExist()) {
            $this->staffProfile()->create([]);
        }
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
     * The staff capabilities this user holds (Admin / Therapist / Lecturer),
     * derived from the backing Spatie roles.
     *
     * @return Collection<int, Capability>
     */
    public function capabilities(): Collection
    {
        return $this->getRoleNames()
            ->map(fn (string $role): ?Capability => Capability::fromRoleName($role))
            ->filter()
            ->values();
    }

    public function hasCapability(Capability $capability): bool
    {
        return $this->hasRole($capability->roleName());
    }

    /**
     * Replace this user's capabilities, leaving any non-capability roles (the
     * `customer` identity) untouched.
     *
     * @param  iterable<Capability>  $capabilities
     */
    public function syncCapabilities(iterable $capabilities): void
    {
        $target = collect($capabilities)->map(function (Capability $c): string {
            Role::findOrCreate($c->roleName());

            return $c->roleName();
        });

        $keep = $this->getRoleNames()
            ->reject(fn (string $role): bool => Capability::fromRoleName($role) !== null);

        $this->syncRoles($keep->merge($target)->unique()->all());
        $this->ensureStaffProfile();
    }

    public function grantCapability(Capability $capability): void
    {
        Role::findOrCreate($capability->roleName());
        $this->assignRole($capability->roleName());
        $this->ensureStaffProfile();
    }

    /**
     * Apply a capability selection made by $actor, enforcing that the actor may
     * only change capabilities they are allowed to assign. Capabilities the
     * actor cannot manage (Admin / SuperAdmin for a non-super-admin) are kept as
     * they were, so a form tamper can neither escalate nor strip privileges.
     *
     * @param  iterable<Capability|string>  $selected
     */
    public function applyCapabilitySelection(iterable $selected, self $actor): void
    {
        $assignable = collect($actor->assignableCapabilities());

        $chosen = collect($selected)
            ->map(fn (Capability|string $c): Capability => $c instanceof Capability ? $c : Capability::from($c))
            ->filter(fn (Capability $c): bool => $assignable->contains($c));

        $locked = $this->capabilities()->reject(fn (Capability $c): bool => $assignable->contains($c));

        $this->syncCapabilities($locked->merge($chosen)->unique());
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasCapability(Capability::SuperAdmin);
    }

    /**
     * Admin-level access. A super-admin is always an admin (a strict superset),
     * so this covers both tiers.
     */
    public function isAdmin(): bool
    {
        return $this->hasCapability(Capability::Admin) || $this->isSuperAdmin();
    }

    /**
     * The capabilities the current user is allowed to grant or revoke on others.
     * Admin and SuperAdmin are reserved for super-admins; anyone managing users
     * may still assign the operational capabilities.
     *
     * @return list<Capability>
     */
    public function assignableCapabilities(): array
    {
        return $this->isSuperAdmin()
            ? Capability::cases()
            : array_values(array_filter(
                Capability::cases(),
                fn (Capability $c): bool => ! $c->requiresSuperAdmin(),
            ));
    }

    /**
     * Whether this user works as a therapist: bookable for 1:1 reservations,
     * shown in the calendar and the booking wizard.
     */
    public function isTherapist(): bool
    {
        return $this->hasCapability(Capability::Therapist);
    }

    public function isLecturer(): bool
    {
        return $this->hasCapability(Capability::Lecturer);
    }

    /**
     * Staff hold at least one capability and belong in the admin panel;
     * everyone else lives in the client zone.
     */
    public function isStaff(): bool
    {
        return $this->hasAnyRole(array_map(fn (Capability $c): string => $c->roleName(), Capability::cases()));
    }

    /**
     * A client-zone identity, independent of staff capabilities — a user may be
     * both a customer and staff (see the `customer` role).
     */
    public function isCustomer(): bool
    {
        return $this->hasRole(self::CUSTOMER_ROLE);
    }

    public function markAsCustomer(): void
    {
        Role::findOrCreate(self::CUSTOMER_ROLE);
        $this->assignRole(self::CUSTOMER_ROLE);
    }

    /**
     * Users who work as therapists (see isTherapist()).
     */
    public function scopeTherapists(Builder $query): Builder
    {
        return $query->whereHas('roles', fn (Builder $q): Builder => $q->where('name', Capability::Therapist->roleName()));
    }

    public function scopeLecturers(Builder $query): Builder
    {
        return $query->whereHas('roles', fn (Builder $q): Builder => $q->where('name', Capability::Lecturer->roleName()));
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereHas('roles', fn (Builder $q): Builder => $q
            ->whereIn('name', array_map(fn (Capability $c): string => $c->roleName(), Capability::cases())));
    }

    public function scopeCustomers(Builder $query): Builder
    {
        return $query->whereHas('roles', fn (Builder $q): Builder => $q->where('name', self::CUSTOMER_ROLE));
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
     * Whether this user may impersonate others in the panel — an admin
     * capability (which includes super-admins).
     */
    public function canImpersonate(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Guards who may be impersonated: nobody may step into a super-admin, and
     * only a super-admin may impersonate another admin. Prevents a plain admin
     * escalating to owner-tier access by impersonating one.
     */
    public function canBeImpersonated(): bool
    {
        $actor = auth()->user();

        if ($this->isSuperAdmin()) {
            return false;
        }

        if ($this->isAdmin()) {
            return $actor instanceof self && $actor->isSuperAdmin();
        }

        return true;
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
