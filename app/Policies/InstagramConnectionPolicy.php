<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InstagramConnection;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class InstagramConnectionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:InstagramConnection');
    }

    public function view(AuthUser $authUser, InstagramConnection $instagramConnection): bool
    {
        return $authUser->can('View:InstagramConnection');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:InstagramConnection');
    }

    public function update(AuthUser $authUser, InstagramConnection $instagramConnection): bool
    {
        return $authUser->can('Update:InstagramConnection');
    }

    public function delete(AuthUser $authUser, InstagramConnection $instagramConnection): bool
    {
        return $authUser->can('Delete:InstagramConnection');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:InstagramConnection');
    }

    public function restore(AuthUser $authUser, InstagramConnection $instagramConnection): bool
    {
        return $authUser->can('Restore:InstagramConnection');
    }

    public function forceDelete(AuthUser $authUser, InstagramConnection $instagramConnection): bool
    {
        return $authUser->can('ForceDelete:InstagramConnection');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:InstagramConnection');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:InstagramConnection');
    }

    public function replicate(AuthUser $authUser, InstagramConnection $instagramConnection): bool
    {
        return $authUser->can('Replicate:InstagramConnection');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:InstagramConnection');
    }
}
