<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ReservationDayWaitlistEntry;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReservationDayWaitlistEntryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ReservationDayWaitlistEntry');
    }

    public function view(AuthUser $authUser, ReservationDayWaitlistEntry $reservationDayWaitlistEntry): bool
    {
        return $authUser->can('View:ReservationDayWaitlistEntry');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ReservationDayWaitlistEntry');
    }

    public function update(AuthUser $authUser, ReservationDayWaitlistEntry $reservationDayWaitlistEntry): bool
    {
        return $authUser->can('Update:ReservationDayWaitlistEntry');
    }

    public function delete(AuthUser $authUser, ReservationDayWaitlistEntry $reservationDayWaitlistEntry): bool
    {
        return $authUser->can('Delete:ReservationDayWaitlistEntry');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ReservationDayWaitlistEntry');
    }

    public function restore(AuthUser $authUser, ReservationDayWaitlistEntry $reservationDayWaitlistEntry): bool
    {
        return $authUser->can('Restore:ReservationDayWaitlistEntry');
    }

    public function forceDelete(AuthUser $authUser, ReservationDayWaitlistEntry $reservationDayWaitlistEntry): bool
    {
        return $authUser->can('ForceDelete:ReservationDayWaitlistEntry');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ReservationDayWaitlistEntry');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ReservationDayWaitlistEntry');
    }

    public function replicate(AuthUser $authUser, ReservationDayWaitlistEntry $reservationDayWaitlistEntry): bool
    {
        return $authUser->can('Replicate:ReservationDayWaitlistEntry');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ReservationDayWaitlistEntry');
    }

}