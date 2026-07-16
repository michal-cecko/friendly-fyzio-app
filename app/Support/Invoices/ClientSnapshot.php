<?php

namespace App\Support\Invoices;

use App\Models\User;

/**
 * The customer identity frozen onto a document at issue time. Prefers the
 * client profile's billing fields (firemní klienti) over the account basics.
 */
final class ClientSnapshot
{
    /**
     * @return array{name: string, email: ?string, phone: ?string, address: ?string, ico: ?string, dic: ?string}
     */
    public static function for(User $client): array
    {
        $profile = $client->clientProfile;

        return [
            'name' => (string) (($profile?->billing_name ?: null) ?? $client->name),
            'email' => $client->email,
            'phone' => $client->phone,
            'address' => $profile?->billing_address,
            'ico' => $profile?->company_ico,
            'dic' => $profile?->company_dic,
        ];
    }
}
