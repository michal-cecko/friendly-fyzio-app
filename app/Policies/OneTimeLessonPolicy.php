<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\OneTimeLesson;
use Illuminate\Auth\Access\HandlesAuthorization;

class OneTimeLessonPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OneTimeLesson');
    }

    public function view(AuthUser $authUser, OneTimeLesson $oneTimeLesson): bool
    {
        return $authUser->can('View:OneTimeLesson');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OneTimeLesson');
    }

    public function update(AuthUser $authUser, OneTimeLesson $oneTimeLesson): bool
    {
        return $authUser->can('Update:OneTimeLesson');
    }

    public function delete(AuthUser $authUser, OneTimeLesson $oneTimeLesson): bool
    {
        return $authUser->can('Delete:OneTimeLesson');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OneTimeLesson');
    }

    public function restore(AuthUser $authUser, OneTimeLesson $oneTimeLesson): bool
    {
        return $authUser->can('Restore:OneTimeLesson');
    }

    public function forceDelete(AuthUser $authUser, OneTimeLesson $oneTimeLesson): bool
    {
        return $authUser->can('ForceDelete:OneTimeLesson');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OneTimeLesson');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OneTimeLesson');
    }

    public function replicate(AuthUser $authUser, OneTimeLesson $oneTimeLesson): bool
    {
        return $authUser->can('Replicate:OneTimeLesson');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OneTimeLesson');
    }

}