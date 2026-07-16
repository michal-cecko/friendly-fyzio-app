<?php

namespace App\Support\Invoices;

use App\Support\Settings;

/**
 * The clinic identity frozen onto a document at issue time, sourced from Settings.
 */
final class SupplierSnapshot
{
    /**
     * @return array{name: string, address: string, ico: string, dic: ?string, vat_payer: bool, email: string, phone: string, iban: string, bank_account: string, registration: ?string}
     */
    public static function current(): array
    {
        return [
            'name' => Settings::supplierName(),
            'address' => Settings::supplierAddress(),
            'ico' => (string) (Settings::get('web.company_id') ?? ''),
            'dic' => Settings::supplierDic() !== '' ? Settings::supplierDic() : null,
            'vat_payer' => Settings::vatPayer(),
            'email' => (string) (Settings::get('web.contact_email') ?? ''),
            'phone' => (string) (Settings::get('web.contact_phone') ?? ''),
            'iban' => Settings::iban(),
            'bank_account' => Settings::bankAccount(),
            'registration' => Settings::supplierRegistration() !== '' ? Settings::supplierRegistration() : null,
        ];
    }
}
