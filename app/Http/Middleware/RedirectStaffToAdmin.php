<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff (admin/therapist) share the customer login but belong in the admin
 * panel. If one reaches the client zone — by typing the URL or following a
 * stale link — bounce them to the admin panel instead of showing them the
 * customer-facing UI. Mirrors RedirectToClientLogin on the admin side.
 */
class RedirectStaffToAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        if ($user instanceof User && $user->isStaff()) {
            return redirect()->to(Filament::getPanel('admin')->getUrl());
        }

        return $next($request);
    }
}
