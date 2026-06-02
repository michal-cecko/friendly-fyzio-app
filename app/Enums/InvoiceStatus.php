<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case New = 'new';
    case Sent = 'sent';
    case Paid = 'paid';
    case Overdue = 'overdue';
}
