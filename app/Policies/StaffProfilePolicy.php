<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\StaffProfile;
use Illuminate\Auth\Access\HandlesAuthorization;

class StaffProfilePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StaffProfile');
    }

    public function view(AuthUser $authUser, StaffProfile $staffProfile): bool
    {
        return $authUser->can('View:StaffProfile');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StaffProfile');
    }

    public function update(AuthUser $authUser, StaffProfile $staffProfile): bool
    {
        return $authUser->can('Update:StaffProfile');
    }

    public function delete(AuthUser $authUser, StaffProfile $staffProfile): bool
    {
        return $authUser->can('Delete:StaffProfile');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:StaffProfile');
    }

    public function restore(AuthUser $authUser, StaffProfile $staffProfile): bool
    {
        return $authUser->can('Restore:StaffProfile');
    }

    public function forceDelete(AuthUser $authUser, StaffProfile $staffProfile): bool
    {
        return $authUser->can('ForceDelete:StaffProfile');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:StaffProfile');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:StaffProfile');
    }

    public function replicate(AuthUser $authUser, StaffProfile $staffProfile): bool
    {
        return $authUser->can('Replicate:StaffProfile');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:StaffProfile');
    }

}