<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CashReceipt;
use Illuminate\Auth\Access\HandlesAuthorization;

class CashReceiptPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CashReceipt');
    }

    public function view(AuthUser $authUser, CashReceipt $cashReceipt): bool
    {
        return $authUser->can('View:CashReceipt');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CashReceipt');
    }

    public function update(AuthUser $authUser, CashReceipt $cashReceipt): bool
    {
        return $authUser->can('Update:CashReceipt');
    }

    public function delete(AuthUser $authUser, CashReceipt $cashReceipt): bool
    {
        return $authUser->can('Delete:CashReceipt');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CashReceipt');
    }

    public function restore(AuthUser $authUser, CashReceipt $cashReceipt): bool
    {
        return $authUser->can('Restore:CashReceipt');
    }

    public function forceDelete(AuthUser $authUser, CashReceipt $cashReceipt): bool
    {
        return $authUser->can('ForceDelete:CashReceipt');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CashReceipt');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CashReceipt');
    }

    public function replicate(AuthUser $authUser, CashReceipt $cashReceipt): bool
    {
        return $authUser->can('Replicate:CashReceipt');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CashReceipt');
    }

}