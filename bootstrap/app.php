<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the reverse proxy (Valet's nginx locally, load balancer in prod) so
        // the app detects HTTPS from X-Forwarded-* headers instead of the proxied http hop.
        $middleware->trustProxies(at: '*');

        // Guests hitting auth-protected pages (the client zone) go to the public
        // login; `return` round-trips them back after signing in.
        $middleware->redirectGuestsTo(fn ($request) => route('public.login', ['return' => $request->getRequestUri()]));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Forward unhandled exceptions to Sentry (SENTRY_LARAVEL_DSN in the environment).
        Integration::handles($exceptions);
    })->create();
