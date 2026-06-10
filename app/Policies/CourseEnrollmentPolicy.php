<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CourseEnrollment;
use Illuminate\Auth\Access\HandlesAuthorization;

class CourseEnrollmentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CourseEnrollment');
    }

    public function view(AuthUser $authUser, CourseEnrollment $courseEnrollment): bool
    {
        return $authUser->can('View:CourseEnrollment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CourseEnrollment');
    }

    public function update(AuthUser $authUser, CourseEnrollment $courseEnrollment): bool
    {
        return $authUser->can('Update:CourseEnrollment');
    }

    public function delete(AuthUser $authUser, CourseEnrollment $courseEnrollment): bool
    {
        return $authUser->can('Delete:CourseEnrollment');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CourseEnrollment');
    }

    public function restore(AuthUser $authUser, CourseEnrollment $courseEnrollment): bool
    {
        return $authUser->can('Restore:CourseEnrollment');
    }

    public function forceDelete(AuthUser $authUser, CourseEnrollment $courseEnrollment): bool
    {
        return $authUser->can('ForceDelete:CourseEnrollment');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CourseEnrollment');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CourseEnrollment');
    }

    public function replicate(AuthUser $authUser, CourseEnrollment $courseEnrollment): bool
    {
        return $authUser->can('Replicate:CourseEnrollment');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CourseEnrollment');
    }

}