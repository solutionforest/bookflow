<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Booking Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure default booking behavior settings.
    |
    */
    'booking' => [
        /*
        |--------------------------------------------------------------------------
        | Default Capacity
        |--------------------------------------------------------------------------
        |
        | The default number of simultaneous bookings allowed for the same resource
        | and time slot. This can be overridden on individual resources by
        | implementing a getCapacity() method.
        |
        */
        'default_capacity' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing Strategies Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure the default pricing strategies and register custom
    | strategies. Each strategy must implement the PricingStrategy interface.
    |
    */
    'pricing' => [
        // Default strategies provided by the package
        'strategies' => [
            'fixed' => \SolutionForest\Bookflow\Services\PricingStrategies\FixedPriceStrategy::class,
            'hour' => \SolutionForest\Bookflow\Services\PricingStrategies\TimeBasedPricingStrategy::class,
            'day' => \SolutionForest\Bookflow\Services\PricingStrategies\TimeBasedPricingStrategy::class,
        ],

        // Custom strategy configurations
        'custom_strategies' => [
            // Add your custom strategies here
            // 'group' => \App\Services\PricingStrategies\GroupBookingStrategy::class,
        ],

        // Default time-based strategy settings
        'time_based' => [
            'round_up' => true, // Whether to round up partial units
            'minimum_units' => 1, // Minimum number of units to charge
        ],
    ],
];
