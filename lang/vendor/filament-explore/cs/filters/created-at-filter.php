<?php

return [
    'label' => 'Datum',
    'form-schema-components' => [
        'preset' => [
            'label' => 'Předvolba',
            'placeholder' => 'Vyberte předvolbu',
            'options' => [
                'last_7_days' => 'Posledních 7 dní',
                'last_30_days' => 'Posledních 30 dní',
                'last_90_days' => 'Poslední 3 měsíce',
                'last_365_days' => 'Posledních 12 měsíců',
                'month_to_date' => 'Tento měsíc',
                'year_to_date' => 'Tento rok',
                'all_time' => 'Celé období',
            ],
        ],
        'starts_at' => [
            'label' => 'Od',
        ],
        'ends_at' => [
            'label' => 'Do',
        ],
    ],
    'indicators' => [
        'last_7_days' => 'Posledních 7 dní',
        'last_30_days' => 'Posledních 30 dní',
        'last_90_days' => 'Poslední 3 měsíce',
        'last_365_days' => 'Posledních 12 měsíců',
        'month_to_date' => 'Tento měsíc',
        'year_to_date' => 'Tento rok',
        'all_time' => 'Celé období',
        'from_date' => 'Od :date',
        'until_date' => 'Do :date',
    ],
];
