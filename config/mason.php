<?php

declare(strict_types=1);

return [
    'generator' => [
        'namespace' => 'App\\Mason',
        'views_path' => 'mason',
    ],
    'preview' => [
        // Custom layout so in-editor brick previews load the public site CSS.
        'layout' => 'mason.preview',
    ],
    'entry' => [
        'layout' => 'mason::iframe-entry',
    ],
    'routes' => [
        'middleware' => ['web', 'auth'],
    ],
];
