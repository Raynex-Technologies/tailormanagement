<?php

return [
    'storefront' => [
        'enabled' => env('MODULE_STOREFRONT_ENABLED', true),
    ],

    'bookings' => [
        'enabled' => env('MODULE_BOOKINGS_ENABLED', true),
    ],

    'orders' => [
        'enabled' => env('MODULE_ORDERS_ENABLED', true),
    ],

    'inventory' => [
        'enabled' => env('MODULE_INVENTORY_ENABLED', true),
    ],

    'installments' => [
        'enabled' => env('MODULE_INSTALLMENTS_ENABLED', true),
    ],
];
