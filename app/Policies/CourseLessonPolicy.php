<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CourseLesson;
use Illuminate\Auth\Access\HandlesAuthorization;

class CourseLessonPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CourseLesson');
    }

    public function view(AuthUser $authUser, CourseLesson $courseLesson): bool
    {
        return $authUser->can('View:CourseLesson');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CourseLesson');
    }

    public function update(AuthUser $authUser, CourseLesson $courseLesson): bool
    {
        return $authUser->can('Update:CourseLesson');
    }

    public function delete(AuthUser $authUser, CourseLesson $courseLesson): bool
    {
        return $authUser->can('Delete:CourseLesson');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CourseLesson');
    }

    public function restore(AuthUser $authUser, CourseLesson $courseLesson): bool
    {
        return $authUser->can('Restore:CourseLesson');
    }

    public function forceDelete(AuthUser $authUser, CourseLesson $courseLesson): bool
    {
        return $authUser->can('ForceDelete:CourseLesson');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CourseLesson');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CourseLesson');
    }

    public function replicate(AuthUser $authUser, CourseLesson $courseLesson): bool
    {
        return $authUser->can('Replicate:CourseLesson');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CourseLesson');
    }

}