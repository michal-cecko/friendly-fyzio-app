<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the public client zone (/muj-ucet): staff belong in the admin panel,
 * deactivated customers are signed out on the spot. Runs after `auth` +
 * `verified`, so an authenticated, e-mail-verified user is guaranteed.
 */
class EnsureZoneCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isStaff()) {
            return redirect()->to('/admin');
        }

        if ($user->isDeactivated()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('public.login')
                ->with('status', 'Váš účet byl deaktivován. Pro obnovení přístupu nás prosím kontaktujte.');
        }

        return $next($request);
    }
}
