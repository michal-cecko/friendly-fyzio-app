<?php

return [
    'label' => 'Size',
    'form-schema-components' => [
        'min_size' => [
            'label' => 'Minimum size',
            'suffix' => 'units',
        ],
        'max_size' => [
            'label' => 'Maximum size',
            'suffix' => 'units',
        ],
        'unit' => [
            'label' => 'Size unit',
            'options' => [
                'bytes' => 'Bytes',
                'kb' => 'KB',
                'mb' => 'MB',
                'gb' => 'GB',
            ],
        ],
    ],
];
