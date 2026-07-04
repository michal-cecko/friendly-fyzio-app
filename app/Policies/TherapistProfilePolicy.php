<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TherapistProfile;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TherapistProfilePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TherapistProfile');
    }

    public function view(AuthUser $authUser, TherapistProfile $therapistProfile): bool
    {
        return $authUser->can('View:TherapistProfile');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TherapistProfile');
    }

    public function update(AuthUser $authUser, TherapistProfile $therapistProfile): bool
    {
        return $authUser->can('Update:TherapistProfile');
    }

    public function delete(AuthUser $authUser, TherapistProfile $therapistProfile): bool
    {
        return $authUser->can('Delete:TherapistProfile');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TherapistProfile');
    }

    public function restore(AuthUser $authUser, TherapistProfile $therapistProfile): bool
    {
        return $authUser->can('Restore:TherapistProfile');
    }

    public function forceDelete(AuthUser $authUser, TherapistProfile $therapistProfile): bool
    {
        return $authUser->can('ForceDelete:TherapistProfile');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TherapistProfile');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TherapistProfile');
    }

    public function replicate(AuthUser $authUser, TherapistProfile $therapistProfile): bool
    {
        return $authUser->can('Replicate:TherapistProfile');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TherapistProfile');
    }
}
