<?php

return [
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
