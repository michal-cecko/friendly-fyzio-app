<?php

namespace App\Enums;

enum ServiceVisibility: string
{
    case Public = 'public';
    case Clients = 'clients';
    case Invite = 'invite';
    case Hidden = 'hidden';
}
