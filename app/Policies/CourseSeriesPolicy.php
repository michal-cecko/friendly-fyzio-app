<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CourseSeries;
use Illuminate\Auth\Access\HandlesAuthorization;

class CourseSeriesPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CourseSeries');
    }

    public function view(AuthUser $authUser, CourseSeries $courseSeries): bool
    {
        return $authUser->can('View:CourseSeries');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CourseSeries');
    }

    public function update(AuthUser $authUser, CourseSeries $courseSeries): bool
    {
        return $authUser->can('Update:CourseSeries');
    }

    public function delete(AuthUser $authUser, CourseSeries $courseSeries): bool
    {
        return $authUser->can('Delete:CourseSeries');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CourseSeries');
    }

    public function restore(AuthUser $authUser, CourseSeries $courseSeries): bool
    {
        return $authUser->can('Restore:CourseSeries');
    }

    public function forceDelete(AuthUser $authUser, CourseSeries $courseSeries): bool
    {
        return $authUser->can('ForceDelete:CourseSeries');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CourseSeries');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CourseSeries');
    }

    public function replicate(AuthUser $authUser, CourseSeries $courseSeries): bool
    {
        return $authUser->can('Replicate:CourseSeries');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CourseSeries');
    }

}