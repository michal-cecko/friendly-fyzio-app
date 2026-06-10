<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\OneTimeLessonBooking;
use Illuminate\Auth\Access\HandlesAuthorization;

class OneTimeLessonBookingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OneTimeLessonBooking');
    }

    public function view(AuthUser $authUser, OneTimeLessonBooking $oneTimeLessonBooking): bool
    {
        return $authUser->can('View:OneTimeLessonBooking');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OneTimeLessonBooking');
    }

    public function update(AuthUser $authUser, OneTimeLessonBooking $oneTimeLessonBooking): bool
    {
        return $authUser->can('Update:OneTimeLessonBooking');
    }

    public function delete(AuthUser $authUser, OneTimeLessonBooking $oneTimeLessonBooking): bool
    {
        return $authUser->can('Delete:OneTimeLessonBooking');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OneTimeLessonBooking');
    }

    public function restore(AuthUser $authUser, OneTimeLessonBooking $oneTimeLessonBooking): bool
    {
        return $authUser->can('Restore:OneTimeLessonBooking');
    }

    public function forceDelete(AuthUser $authUser, OneTimeLessonBooking $oneTimeLessonBooking): bool
    {
        return $authUser->can('ForceDelete:OneTimeLessonBooking');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OneTimeLessonBooking');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OneTimeLessonBooking');
    }

    public function replicate(AuthUser $authUser, OneTimeLessonBooking $oneTimeLessonBooking): bool
    {
        return $authUser->can('Replicate:OneTimeLessonBooking');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OneTimeLessonBooking');
    }

}