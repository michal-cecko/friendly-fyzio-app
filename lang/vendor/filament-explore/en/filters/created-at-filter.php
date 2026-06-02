<?php

return [
    'label' => 'Date',
    'form-schema-components' => [
        'preset' => [
            'label' => 'Preset',
            'placeholder' => 'Select a preset',
            'options' => [
                'last_7_days' => 'Last 7 days',
                'last_30_days' => 'Last 30 days',
                'last_90_days' => 'Last 3 months',
                'last_365_days' => 'Last 12 months',
                'month_to_date' => 'This month',
                'year_to_date' => 'This year',
                'all_time' => 'All time',
            ],
        ],
        'starts_at' => [
            'label' => 'From',
        ],
        'ends_at' => [
            'label' => 'To',
        ],
    ],
    'indicators' => [
        'last_7_days' => 'Last 7 days',
        'last_30_days' => 'Last 30 days',
        'last_90_days' => 'Last 3 months',
        'last_365_days' => 'Last 12 months',
        'month_to_date' => 'This month',
        'year_to_date' => 'This year',
        'all_time' => 'All time',
        'from_date' => 'From :date',
        'until_date' => 'Until :date',
    ],
];
