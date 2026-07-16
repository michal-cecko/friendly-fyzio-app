<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InvoiceSeries;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class InvoiceSeriesPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:InvoiceSeries');
    }

    public function view(AuthUser $authUser, InvoiceSeries $invoiceSeries): bool
    {
        return $authUser->can('View:InvoiceSeries');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:InvoiceSeries');
    }

    public function update(AuthUser $authUser, InvoiceSeries $invoiceSeries): bool
    {
        return $authUser->can('Update:InvoiceSeries');
    }

    public function delete(AuthUser $authUser, InvoiceSeries $invoiceSeries): bool
    {
        return $authUser->can('Delete:InvoiceSeries');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:InvoiceSeries');
    }

    public function restore(AuthUser $authUser, InvoiceSeries $invoiceSeries): bool
    {
        return $authUser->can('Restore:InvoiceSeries');
    }

    public function forceDelete(AuthUser $authUser, InvoiceSeries $invoiceSeries): bool
    {
        return $authUser->can('ForceDelete:InvoiceSeries');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:InvoiceSeries');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:InvoiceSeries');
    }

    public function replicate(AuthUser $authUser, InvoiceSeries $invoiceSeries): bool
    {
        return $authUser->can('Replicate:InvoiceSeries');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:InvoiceSeries');
    }
}
