<?php

namespace App\Support\MailerLite;

/**
 * Outcome of a subscribe attempt against MailerLite.
 */
enum SubscribeResult
{
    case Subscribed;

    case AlreadySubscribed;
}
