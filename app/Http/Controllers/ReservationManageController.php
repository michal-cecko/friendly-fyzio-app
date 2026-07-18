<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Support\Reservations\ClientReservationActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The single passwordless "manage reservation" page, reached via one signed magic
 * link ({@see Reservation::manageUrl()}). It hosts every customer action for a
 * reservation; the actions themselves live in {@see ClientReservationActions},
 * shared with the authenticated client zone. The link expires at the visit start,
 * so no action is possible afterwards.
 *
 * GET only renders the state-appropriate page; opening the link — including by e-mail
 * scanners — never mutates. Each action is an explicit POST, then a redirect back to
 * the signed GET URL (PRG) so a refresh can't re-submit.
 */
class ReservationManageController extends Controller
{
    public function show(Reservation $reservation): View
    {
        return view('reservations.manage', [
            'reservation' => $reservation->loadMissing('service.cancellationRule', 'therapist.user', 'client', 'payments'),
        ]);
    }

    public function submit(Request $request, Reservation $reservation, ClientReservationActions $actions): RedirectResponse
    {
        match ($request->input('action')) {
            'confirm' => $actions->confirm($reservation),
            'cancel' => $actions->cancelFree($reservation),
            'pay' => $actions->cancelAndPay($reservation),
            'doctor' => $actions->cancelWithDoctorNote($reservation),
            'deactivate' => $actions->cancelAndDeactivate($reservation),
            default => null,
        };

        return redirect()->to($reservation->manageUrl());
    }
}
