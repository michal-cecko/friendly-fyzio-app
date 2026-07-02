<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validační jazykové řádky
    |--------------------------------------------------------------------------
    |
    | Následující jazykové řádky obsahují výchozí chybové zprávy použité třídou
    | validátoru. Některá pravidla mají více variant, například pravidla velikosti.
    | Zprávy si můžete libovolně upravit.
    |
    */

    'accepted' => 'Pole :attribute musí být přijato.',
    'accepted_if' => 'Pole :attribute musí být přijato, když :other je :value.',
    'active_url' => 'Pole :attribute musí být platná URL adresa.',
    'after' => 'Pole :attribute musí být datum po :date.',
    'after_or_equal' => 'Pole :attribute musí být datum po nebo shodné s :date.',
    'alpha' => 'Pole :attribute může obsahovat pouze písmena.',
    'alpha_dash' => 'Pole :attribute může obsahovat pouze písmena, číslice, pomlčky a podtržítka.',
    'alpha_num' => 'Pole :attribute může obsahovat pouze písmena a číslice.',
    'any_of' => 'Pole :attribute je neplatné.',
    'array' => 'Pole :attribute musí být pole.',
    'ascii' => 'Pole :attribute může obsahovat pouze jednobajtové alfanumerické znaky a symboly.',
    'before' => 'Pole :attribute musí být datum před :date.',
    'before_or_equal' => 'Pole :attribute musí být datum před nebo shodné s :date.',
    'between' => [
        'array' => 'Pole :attribute musí mít mezi :min a :max položkami.',
        'file' => 'Pole :attribute musí být mezi :min a :max kilobajty.',
        'numeric' => 'Pole :attribute musí být mezi :min a :max.',
        'string' => 'Pole :attribute musí mít mezi :min a :max znaky.',
    ],
    'boolean' => 'Pole :attribute musí být true nebo false.',
    'can' => 'Pole :attribute obsahuje nepovolenou hodnotu.',
    'confirmed' => 'Potvrzení pole :attribute se neshoduje.',
    'contains' => 'Pole :attribute postrádá požadovanou hodnotu.',
    'current_password' => 'Heslo je nesprávné.',
    'date' => 'Pole :attribute musí být platné datum.',
    'date_equals' => 'Pole :attribute musí být datum shodné s :date.',
    'date_format' => 'Pole :attribute musí odpovídat formátu :format.',
    'decimal' => 'Pole :attribute musí mít :decimal desetinných míst.',
    'declined' => 'Pole :attribute musí být odmítnuto.',
    'declined_if' => 'Pole :attribute musí být odmítnuto, když :other je :value.',
    'different' => 'Pole :attribute a :other se musí lišit.',
    'digits' => 'Pole :attribute musí mít :digits číslic.',
    'digits_between' => 'Pole :attribute musí mít mezi :min a :max číslicemi.',
    'dimensions' => 'Pole :attribute má neplatné rozměry obrázku.',
    'distinct' => 'Pole :attribute má duplicitní hodnotu.',
    'doesnt_contain' => 'Pole :attribute nesmí obsahovat žádnou z hodnot: :values.',
    'doesnt_end_with' => 'Pole :attribute nesmí končit na: :values.',
    'doesnt_start_with' => 'Pole :attribute nesmí začínat na: :values.',
    'email' => 'Pole :attribute musí být platná e-mailová adresa.',
    'encoding' => 'Pole :attribute musí být kódováno v :encoding.',
    'ends_with' => 'Pole :attribute musí končit na: :values.',
    'enum' => 'Zvolená hodnota :attribute je neplatná.',
    'exists' => 'Zvolená hodnota :attribute je neplatná.',
    'extensions' => 'Pole :attribute musí mít jednu z přípon: :values.',
    'file' => 'Pole :attribute musí být soubor.',
    'filled' => 'Pole :attribute musí být vyplněno.',
    'gt' => [
        'array' => 'Pole :attribute musí mít více než :value položek.',
        'file' => 'Pole :attribute musí být větší než :value kilobajtů.',
        'numeric' => 'Pole :attribute musí být větší než :value.',
        'string' => 'Pole :attribute musí mít více než :value znaků.',
    ],
    'gte' => [
        'array' => 'Pole :attribute musí mít :value nebo více položek.',
        'file' => 'Pole :attribute musí být větší nebo rovno :value kilobajtům.',
        'numeric' => 'Pole :attribute musí být větší nebo rovno :value.',
        'string' => 'Pole :attribute musí mít :value nebo více znaků.',
    ],
    'hex_color' => 'Pole :attribute musí být platná hexadecimální barva.',
    'image' => 'Pole :attribute musí být obrázek.',
    'in' => 'Zvolená hodnota :attribute je neplatná.',
    'in_array' => 'Pole :attribute musí existovat v :other.',
    'in_array_keys' => 'Pole :attribute musí obsahovat alespoň jeden z klíčů: :values.',
    'integer' => 'Pole :attribute musí být celé číslo.',
    'ip' => 'Pole :attribute musí být platná IP adresa.',
    'ipv4' => 'Pole :attribute musí být platná IPv4 adresa.',
    'ipv6' => 'Pole :attribute musí být platná IPv6 adresa.',
    'json' => 'Pole :attribute musí být platný JSON řetězec.',
    'list' => 'Pole :attribute musí být seznam.',
    'lowercase' => 'Pole :attribute musí být malými písmeny.',
    'lt' => [
        'array' => 'Pole :attribute musí mít méně než :value položek.',
        'file' => 'Pole :attribute musí být menší než :value kilobajtů.',
        'numeric' => 'Pole :attribute musí být menší než :value.',
        'string' => 'Pole :attribute musí mít méně než :value znaků.',
    ],
    'lte' => [
        'array' => 'Pole :attribute nesmí mít více než :value položek.',
        'file' => 'Pole :attribute musí být menší nebo rovno :value kilobajtům.',
        'numeric' => 'Pole :attribute musí být menší nebo rovno :value.',
        'string' => 'Pole :attribute musí mít :value nebo méně znaků.',
    ],
    'mac_address' => 'Pole :attribute musí být platná MAC adresa.',
    'max' => [
        'array' => 'Pole :attribute nesmí mít více než :max položek.',
        'file' => 'Pole :attribute nesmí být větší než :max kilobajtů.',
        'numeric' => 'Pole :attribute nesmí být větší než :max.',
        'string' => 'Pole :attribute nesmí mít více než :max znaků.',
    ],
    'max_digits' => 'Pole :attribute nesmí mít více než :max číslic.',
    'mimes' => 'Pole :attribute musí být soubor typu: :values.',
    'mimetypes' => 'Pole :attribute musí být soubor typu: :values.',
    'min' => [
        'array' => 'Pole :attribute musí mít alespoň :min položek.',
        'file' => 'Pole :attribute musí být alespoň :min kilobajtů.',
        'numeric' => 'Pole :attribute musí být alespoň :min.',
        'string' => 'Pole :attribute musí mít alespoň :min znaků.',
    ],
    'min_digits' => 'Pole :attribute musí mít alespoň :min číslic.',
    'missing' => 'Pole :attribute nesmí být přítomno.',
    'missing_if' => 'Pole :attribute nesmí být přítomno, když :other je :value.',
    'missing_unless' => 'Pole :attribute nesmí být přítomno, pokud :other není :value.',
    'missing_with' => 'Pole :attribute nesmí být přítomno, když je přítomno :values.',
    'missing_with_all' => 'Pole :attribute nesmí být přítomno, když jsou přítomna :values.',
    'multiple_of' => 'Pole :attribute musí být násobkem :value.',
    'not_in' => 'Zvolená hodnota :attribute je neplatná.',
    'not_regex' => 'Formát pole :attribute je neplatný.',
    'numeric' => 'Pole :attribute musí být číslo.',
    'password' => [
        'letters' => 'Pole :attribute musí obsahovat alespoň jedno písmeno.',
        'mixed' => 'Pole :attribute musí obsahovat alespoň jedno velké a jedno malé písmeno.',
        'numbers' => 'Pole :attribute musí obsahovat alespoň jednu číslici.',
        'symbols' => 'Pole :attribute musí obsahovat alespoň jeden symbol.',
        'uncompromised' => 'Zadaná hodnota :attribute se objevila v úniku dat. Zvolte prosím jinou hodnotu :attribute.',
    ],
    'present' => 'Pole :attribute musí být přítomno.',
    'present_if' => 'Pole :attribute musí být přítomno, když :other je :value.',
    'present_unless' => 'Pole :attribute musí být přítomno, pokud :other není :value.',
    'present_with' => 'Pole :attribute musí být přítomno, když je přítomno :values.',
    'present_with_all' => 'Pole :attribute musí být přítomno, když jsou přítomna :values.',
    'prohibited' => 'Pole :attribute je zakázáno.',
    'prohibited_if' => 'Pole :attribute je zakázáno, když :other je :value.',
    'prohibited_if_accepted' => 'Pole :attribute je zakázáno, když je :other přijato.',
    'prohibited_if_declined' => 'Pole :attribute je zakázáno, když je :other odmítnuto.',
    'prohibited_unless' => 'Pole :attribute je zakázáno, pokud :other není v :values.',
    'prohibits' => 'Pole :attribute zakazuje přítomnost :other.',
    'regex' => 'Formát pole :attribute je neplatný.',
    'required' => 'Pole :attribute je povinné.',
    'required_array_keys' => 'Pole :attribute musí obsahovat záznamy pro: :values.',
    'required_if' => 'Pole :attribute je povinné, když :other je :value.',
    'required_if_accepted' => 'Pole :attribute je povinné, když je :other přijato.',
    'required_if_declined' => 'Pole :attribute je povinné, když je :other odmítnuto.',
    'required_unless' => 'Pole :attribute je povinné, pokud :other není v :values.',
    'required_with' => 'Pole :attribute je povinné, když je přítomno :values.',
    'required_with_all' => 'Pole :attribute je povinné, když jsou přítomna :values.',
    'required_without' => 'Pole :attribute je povinné, když není přítomno :values.',
    'required_without_all' => 'Pole :attribute je povinné, když není přítomno žádné z :values.',
    'same' => 'Pole :attribute se musí shodovat s :other.',
    'size' => [
        'array' => 'Pole :attribute musí obsahovat :size položek.',
        'file' => 'Pole :attribute musí mít :size kilobajtů.',
        'numeric' => 'Pole :attribute musí být :size.',
        'string' => 'Pole :attribute musí mít :size znaků.',
    ],
    'starts_with' => 'Pole :attribute musí začínat na: :values.',
    'string' => 'Pole :attribute musí být řetězec.',
    'timezone' => 'Pole :attribute musí být platné časové pásmo.',
    'unique' => 'Hodnota :attribute je již obsazena.',
    'uploaded' => 'Nahrání pole :attribute se nezdařilo.',
    'uppercase' => 'Pole :attribute musí být velkými písmeny.',
    'url' => 'Pole :attribute musí být platná URL adresa.',
    'ulid' => 'Pole :attribute musí být platné ULID.',
    'uuid' => 'Pole :attribute musí být platné UUID.',

    /*
    |--------------------------------------------------------------------------
    | Vlastní validační jazykové řádky
    |--------------------------------------------------------------------------
    |
    | Zde můžete zadat vlastní validační zprávy pro atributy pomocí konvence
    | „atribut.pravidlo“ pro pojmenování řádků.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vlastní názvy atributů
    |--------------------------------------------------------------------------
    |
    | Následující řádky nahrazují zástupný symbol atributu čitelnějším názvem.
    | Uvádíme snake_case i camelCase varianty kvůli Livewire vlastnostem.
    |
    */

    'attributes' => [
        'name' => 'jméno',
        'first_name' => 'jméno',
        'firstName' => 'jméno',
        'last_name' => 'příjmení',
        'lastName' => 'příjmení',
        'email' => 'e-mail',
        'loginEmail' => 'e-mail',
        'password' => 'heslo',
        'loginPassword' => 'heslo',
        'password_confirmation' => 'potvrzení hesla',
        'phone' => 'telefon',
        'phoneConfirm' => 'telefon pro kontrolu',
        'note' => 'poznámka',
        'title' => 'název',
        'slug' => 'slug',
        'description' => 'popis',
        'content' => 'obsah',
        'date' => 'datum',
        'time' => 'čas',
        'agreeCancellation' => 'souhlas se storno podmínkami',
        'newsletter' => 'odběr novinek',
    ],

];
