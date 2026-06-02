<?php

return [
    'label' => 'Velikost',
    'form-schema-components' => [
        'min_size' => [
            'label' => 'Minimální velikost',
            'suffix' => 'jednotek',
        ],
        'max_size' => [
            'label' => 'Maximální velikost',
            'suffix' => 'jednotek',
        ],
        'unit' => [
            'label' => 'Jednotka velikosti',
            'options' => [
                'bytes' => 'Bajty',
                'kb' => 'KB',
                'mb' => 'MB',
                'gb' => 'GB',
            ],
        ],
    ],
];
