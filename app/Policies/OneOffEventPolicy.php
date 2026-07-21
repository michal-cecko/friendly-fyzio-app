<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OneOffEvent;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OneOffEventPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OneOffEvent');
    }

    public function view(AuthUser $authUser, OneOffEvent $oneOffEvent): bool
    {
        return $authUser->can('View:OneOffEvent');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OneOffEvent');
    }

    public function update(AuthUser $authUser, OneOffEvent $oneOffEvent): bool
    {
        return $authUser->can('Update:OneOffEvent');
    }

    public function delete(AuthUser $authUser, OneOffEvent $oneOffEvent): bool
    {
        return $authUser->can('Delete:OneOffEvent');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OneOffEvent');
    }

    public function restore(AuthUser $authUser, OneOffEvent $oneOffEvent): bool
    {
        return $authUser->can('Restore:OneOffEvent');
    }

    public function forceDelete(AuthUser $authUser, OneOffEvent $oneOffEvent): bool
    {
        return $authUser->can('ForceDelete:OneOffEvent');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OneOffEvent');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OneOffEvent');
    }

    public function replicate(AuthUser $authUser, OneOffEvent $oneOffEvent): bool
    {
        return $authUser->can('Replicate:OneOffEvent');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OneOffEvent');
    }
}
