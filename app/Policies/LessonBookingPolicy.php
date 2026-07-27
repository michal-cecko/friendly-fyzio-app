<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LessonBooking;
use Illuminate\Auth\Access\HandlesAuthorization;

class LessonBookingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LessonBooking');
    }

    public function view(AuthUser $authUser, LessonBooking $lessonBooking): bool
    {
        return $authUser->can('View:LessonBooking');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LessonBooking');
    }

    public function update(AuthUser $authUser, LessonBooking $lessonBooking): bool
    {
        return $authUser->can('Update:LessonBooking');
    }

    public function delete(AuthUser $authUser, LessonBooking $lessonBooking): bool
    {
        return $authUser->can('Delete:LessonBooking');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LessonBooking');
    }

    public function restore(AuthUser $authUser, LessonBooking $lessonBooking): bool
    {
        return $authUser->can('Restore:LessonBooking');
    }

    public function forceDelete(AuthUser $authUser, LessonBooking $lessonBooking): bool
    {
        return $authUser->can('ForceDelete:LessonBooking');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LessonBooking');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LessonBooking');
    }

    public function replicate(AuthUser $authUser, LessonBooking $lessonBooking): bool
    {
        return $authUser->can('Replicate:LessonBooking');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LessonBooking');
    }

}