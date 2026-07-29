<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Specialization;
use Illuminate\Auth\Access\HandlesAuthorization;

class SpecializationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Specialization');
    }

    public function view(AuthUser $authUser, Specialization $specialization): bool
    {
        return $authUser->can('View:Specialization');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Specialization');
    }

    public function update(AuthUser $authUser, Specialization $specialization): bool
    {
        return $authUser->can('Update:Specialization');
    }

    public function delete(AuthUser $authUser, Specialization $specialization): bool
    {
        return $authUser->can('Delete:Specialization');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Specialization');
    }

    public function restore(AuthUser $authUser, Specialization $specialization): bool
    {
        return $authUser->can('Restore:Specialization');
    }

    public function forceDelete(AuthUser $authUser, Specialization $specialization): bool
    {
        return $authUser->can('ForceDelete:Specialization');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Specialization');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Specialization');
    }

    public function replicate(AuthUser $authUser, Specialization $specialization): bool
    {
        return $authUser->can('Replicate:Specialization');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Specialization');
    }

}