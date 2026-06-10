<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LessonAttendance;
use Illuminate\Auth\Access\HandlesAuthorization;

class LessonAttendancePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LessonAttendance');
    }

    public function view(AuthUser $authUser, LessonAttendance $lessonAttendance): bool
    {
        return $authUser->can('View:LessonAttendance');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LessonAttendance');
    }

    public function update(AuthUser $authUser, LessonAttendance $lessonAttendance): bool
    {
        return $authUser->can('Update:LessonAttendance');
    }

    public function delete(AuthUser $authUser, LessonAttendance $lessonAttendance): bool
    {
        return $authUser->can('Delete:LessonAttendance');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LessonAttendance');
    }

    public function restore(AuthUser $authUser, LessonAttendance $lessonAttendance): bool
    {
        return $authUser->can('Restore:LessonAttendance');
    }

    public function forceDelete(AuthUser $authUser, LessonAttendance $lessonAttendance): bool
    {
        return $authUser->can('ForceDelete:LessonAttendance');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LessonAttendance');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LessonAttendance');
    }

    public function replicate(AuthUser $authUser, LessonAttendance $lessonAttendance): bool
    {
        return $authUser->can('Replicate:LessonAttendance');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LessonAttendance');
    }

}