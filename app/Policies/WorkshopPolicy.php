<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Workshop;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorkshopPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Workshop');
    }

    public function view(AuthUser $authUser, Workshop $workshop): bool
    {
        return $authUser->can('View:Workshop');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Workshop');
    }

    public function update(AuthUser $authUser, Workshop $workshop): bool
    {
        return $authUser->can('Update:Workshop');
    }

    public function delete(AuthUser $authUser, Workshop $workshop): bool
    {
        return $authUser->can('Delete:Workshop');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Workshop');
    }

    public function restore(AuthUser $authUser, Workshop $workshop): bool
    {
        return $authUser->can('Restore:Workshop');
    }

    public function forceDelete(AuthUser $authUser, Workshop $workshop): bool
    {
        return $authUser->can('ForceDelete:Workshop');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Workshop');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Workshop');
    }

    public function replicate(AuthUser $authUser, Workshop $workshop): bool
    {
        return $authUser->can('Replicate:Workshop');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Workshop');
    }

}