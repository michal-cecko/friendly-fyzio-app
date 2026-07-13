<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ContactInquiry;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContactInquiryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ContactInquiry');
    }

    public function view(AuthUser $authUser, ContactInquiry $contactInquiry): bool
    {
        return $authUser->can('View:ContactInquiry');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ContactInquiry');
    }

    public function update(AuthUser $authUser, ContactInquiry $contactInquiry): bool
    {
        return $authUser->can('Update:ContactInquiry');
    }

    public function delete(AuthUser $authUser, ContactInquiry $contactInquiry): bool
    {
        return $authUser->can('Delete:ContactInquiry');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ContactInquiry');
    }

    public function restore(AuthUser $authUser, ContactInquiry $contactInquiry): bool
    {
        return $authUser->can('Restore:ContactInquiry');
    }

    public function forceDelete(AuthUser $authUser, ContactInquiry $contactInquiry): bool
    {
        return $authUser->can('ForceDelete:ContactInquiry');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ContactInquiry');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ContactInquiry');
    }

    public function replicate(AuthUser $authUser, ContactInquiry $contactInquiry): bool
    {
        return $authUser->can('Replicate:ContactInquiry');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ContactInquiry');
    }

}