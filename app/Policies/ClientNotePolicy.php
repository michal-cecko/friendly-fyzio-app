<?php

namespace App\Policies;

use App\Models\ClientNote;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ClientNotePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ClientNote');
    }

    public function view(AuthUser $authUser, ClientNote $clientNote): bool
    {
        return $authUser->can('View:ClientNote');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ClientNote');
    }

    /**
     * Only the note's author (or an admin) may change it; super admins bypass via Gate::before.
     */
    public function update(AuthUser $authUser, ClientNote $clientNote): bool
    {
        return $authUser->can('Update:ClientNote')
            && ($clientNote->author_id === $authUser->getKey() || $authUser->hasRole('admin'));
    }

    public function delete(AuthUser $authUser, ClientNote $clientNote): bool
    {
        return $authUser->can('Delete:ClientNote')
            && ($clientNote->author_id === $authUser->getKey() || $authUser->hasRole('admin'));
    }
}
