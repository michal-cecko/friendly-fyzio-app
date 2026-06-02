<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Qr = 'qr';
    case Cash = 'cash';
    case Credit = 'credit';
}
