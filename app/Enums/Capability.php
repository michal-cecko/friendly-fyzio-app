<?php

namespace App\Enums;

use App\Models\User;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What a member of staff is allowed to do. Capabilities compose freely — a
 * person may hold any subset — and are stored as Spatie roles (the mechanism
 * Filament Shield already authorises from). Never read the Spatie roles
 * directly; go through the typed helpers on {@see User}.
 *
 *  - SuperAdmin → the clinic owner tier. Backed by the Shield `super_admin`
 *                 role (Gate::before bypass = unconditional access). Only a
 *                 super-admin may grant SuperAdmin/Admin or create/delete
 *                 admins (see User::assignableCapabilities() and UserPolicy).
 *  - Admin      → broad management access via the `admin` role (holds every
 *                 permission, but still passes through policies). Does NOT by
 *                 itself make you bookable or a lecturer.
 *  - Therapist  → bookable for 1:1 reservations; appears in the calendar and
 *                 the booking wizard; owns work blocks.
 *  - Lecturer   → assignable as the instructor of a course, series, lesson or
 *                 one-off event.
 *
 * "Customer" is deliberately NOT a capability — it is an independent client
 * identity (see User::isCustomer()) that can coexist with any of these.
 */
enum Capability: string implements HasColor, HasLabel
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Therapist = 'therapist';
    case Lecturer = 'lecturer';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrátor',
            self::Admin => 'Administrátor',
            self::Therapist => 'Terapeut',
            self::Lecturer => 'Lektor',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::Admin => 'warning',
            self::Therapist => 'info',
            self::Lecturer => 'success',
        };
    }

    /**
     * The Spatie/Shield role backing this capability. SuperAdmin maps to the
     * configured super_admin role so its all-access bypass is preserved.
     */
    public function roleName(): string
    {
        return match ($this) {
            self::SuperAdmin => config('filament-shield.super_admin.name', 'super_admin'),
            default => $this->value,
        };
    }

    /**
     * The capability a Spatie role name maps back to, or null for non-capability
     * roles (e.g. the `customer` identity role).
     */
    public static function fromRoleName(string $role): ?self
    {
        foreach (self::cases() as $capability) {
            if ($capability->roleName() === $role) {
                return $capability;
            }
        }

        return null;
    }

    /**
     * Capabilities only a super-admin may grant or revoke. Everything else can
     * be managed by any admin.
     *
     * @return list<self>
     */
    public static function superAdminOnly(): array
    {
        return [self::SuperAdmin, self::Admin];
    }

    public function requiresSuperAdmin(): bool
    {
        return in_array($this, self::superAdminOnly(), true);
    }
}
