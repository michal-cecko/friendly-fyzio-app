<?php

namespace App\Http\Controllers;

use App\Support\MailerLite\MailerLiteClient;
use App\Support\MailerLite\MailerLiteException;
use App\Support\MailerLite\SubscribeResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Handles public newsletter signups from the brick and footer forms. The call is
 * synchronous so the visitor immediately sees which of the three outcomes
 * happened: newly subscribed, already subscribed, or an error.
 */
class NewsletterController extends Controller
{
    public function __invoke(Request $request, MailerLiteClient $client): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $result = $client->subscribe($validated['email']);
        } catch (MailerLiteException $exception) {
            report($exception);

            return back()->with('newsletter_status', 'error');
        }

        return back()->with('newsletter_status', match ($result) {
            SubscribeResult::Subscribed => 'subscribed',
            SubscribeResult::AlreadySubscribed => 'already',
        });
    }
}
