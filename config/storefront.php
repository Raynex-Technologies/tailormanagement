<?php

return [
    'cart' => [
        'cookie' => env('STOREFRONT_CART_COOKIE', 'tailorpro_cart'),
        'guest_lifetime_minutes' => (int) env('STOREFRONT_GUEST_CART_MINUTES', 20160), // 14 days
    ],

    'shipping' => [
        'quote_cache_minutes' => (int) env('STOREFRONT_SHIPPING_QUOTE_CACHE_MINUTES', 10),
    ],

    'custom_order_stages' => [
        ['key' => 'order_received', 'label' => 'Order Received'],
        ['key' => 'measurement_confirmed', 'label' => 'Measurement Confirmed'],
        ['key' => 'requirements_confirmed', 'label' => 'Fabric / Requirements Confirmed'],
        ['key' => 'in_production', 'label' => 'In Production'],
        ['key' => 'fitting_review', 'label' => 'Fitting / Review'],
        ['key' => 'finishing', 'label' => 'Finishing'],
        ['key' => 'ready_for_pickup', 'label' => 'Ready for Pickup / Delivery'],
    ],
];

