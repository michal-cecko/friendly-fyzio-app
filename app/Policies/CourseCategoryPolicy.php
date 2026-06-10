<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CourseCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class CourseCategoryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CourseCategory');
    }

    public function view(AuthUser $authUser, CourseCategory $courseCategory): bool
    {
        return $authUser->can('View:CourseCategory');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CourseCategory');
    }

    public function update(AuthUser $authUser, CourseCategory $courseCategory): bool
    {
        return $authUser->can('Update:CourseCategory');
    }

    public function delete(AuthUser $authUser, CourseCategory $courseCategory): bool
    {
        return $authUser->can('Delete:CourseCategory');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CourseCategory');
    }

    public function restore(AuthUser $authUser, CourseCategory $courseCategory): bool
    {
        return $authUser->can('Restore:CourseCategory');
    }

    public function forceDelete(AuthUser $authUser, CourseCategory $courseCategory): bool
    {
        return $authUser->can('ForceDelete:CourseCategory');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CourseCategory');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CourseCategory');
    }

    public function replicate(AuthUser $authUser, CourseCategory $courseCategory): bool
    {
        return $authUser->can('Replicate:CourseCategory');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CourseCategory');
    }

}