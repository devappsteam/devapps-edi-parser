<?php

return [
    'enabled' => env('USER_MODULE_ENABLED', true),
    'pagination' => [
        'per_page' => env('USER_PER_PAGE', 15),
        'max_per_page' => env('USER_MAX_PER_PAGE', 100),
    ],
    'views' => [
        'namespace' => 'user',
    ],
];
