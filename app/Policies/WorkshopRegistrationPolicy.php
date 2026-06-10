<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\WorkshopRegistration;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorkshopRegistrationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WorkshopRegistration');
    }

    public function view(AuthUser $authUser, WorkshopRegistration $workshopRegistration): bool
    {
        return $authUser->can('View:WorkshopRegistration');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WorkshopRegistration');
    }

    public function update(AuthUser $authUser, WorkshopRegistration $workshopRegistration): bool
    {
        return $authUser->can('Update:WorkshopRegistration');
    }

    public function delete(AuthUser $authUser, WorkshopRegistration $workshopRegistration): bool
    {
        return $authUser->can('Delete:WorkshopRegistration');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:WorkshopRegistration');
    }

    public function restore(AuthUser $authUser, WorkshopRegistration $workshopRegistration): bool
    {
        return $authUser->can('Restore:WorkshopRegistration');
    }

    public function forceDelete(AuthUser $authUser, WorkshopRegistration $workshopRegistration): bool
    {
        return $authUser->can('ForceDelete:WorkshopRegistration');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WorkshopRegistration');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WorkshopRegistration');
    }

    public function replicate(AuthUser $authUser, WorkshopRegistration $workshopRegistration): bool
    {
        return $authUser->can('Replicate:WorkshopRegistration');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WorkshopRegistration');
    }

}