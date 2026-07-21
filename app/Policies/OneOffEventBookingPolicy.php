<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\OneOffEventBooking;
use Illuminate\Auth\Access\HandlesAuthorization;

class OneOffEventBookingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OneOffEventBooking');
    }

    public function view(AuthUser $authUser, OneOffEventBooking $oneOffEventBooking): bool
    {
        return $authUser->can('View:OneOffEventBooking');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OneOffEventBooking');
    }

    public function update(AuthUser $authUser, OneOffEventBooking $oneOffEventBooking): bool
    {
        return $authUser->can('Update:OneOffEventBooking');
    }

    public function delete(AuthUser $authUser, OneOffEventBooking $oneOffEventBooking): bool
    {
        return $authUser->can('Delete:OneOffEventBooking');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OneOffEventBooking');
    }

    public function restore(AuthUser $authUser, OneOffEventBooking $oneOffEventBooking): bool
    {
        return $authUser->can('Restore:OneOffEventBooking');
    }

    public function forceDelete(AuthUser $authUser, OneOffEventBooking $oneOffEventBooking): bool
    {
        return $authUser->can('ForceDelete:OneOffEventBooking');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OneOffEventBooking');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OneOffEventBooking');
    }

    public function replicate(AuthUser $authUser, OneOffEventBooking $oneOffEventBooking): bool
    {
        return $authUser->can('Replicate:OneOffEventBooking');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OneOffEventBooking');
    }

}