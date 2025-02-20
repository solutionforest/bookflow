# BookFlow

[![Latest Version on Packagist](https://img.shields.io/packagist/v/lam0819/bookflow.svg?style=flat-square)](https://packagist.org/packages/lam0819/bookflow)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/lam0819/bookflow/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/lam0819/bookflow/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/lam0819/bookflow/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/lam0819/bookflow/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/lam0819/bookflow.svg?style=flat-square)](https://packagist.org/packages/lam0819/bookflow)

BookFlow is a flexible Laravel package for managing bookings and pricing strategies. It provides a robust foundation for implementing booking systems with customizable pricing calculations.

## Features

- Easy booking management
- Flexible pricing strategies (Fixed, Hourly, Daily)
- Customizable time-based pricing
- Extensible architecture for custom pricing strategies

## Installation

You can install the package via composer:

```bash
composer require lam0819/bookflow
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="bookflow-migrations"
php artisan migrate
```

Publish the config file:

```bash
php artisan vendor:publish --tag="bookflow-config"
```

Optionally, publish the views:

```bash
php artisan vendor:publish --tag="bookflow-views"
```

## Configuration

After publishing the config file, you can configure the pricing strategies in `config/bookflow.php`:

```php
return [
    'pricing' => [
        'strategies' => [
            'fixed' => \SolutionForest\Bookflow\Services\PricingStrategies\FixedPriceStrategy::class,
            'hour' => \SolutionForest\Bookflow\Services\PricingStrategies\TimeBasedPricingStrategy::class,
            'day' => \SolutionForest\Bookflow\Services\PricingStrategies\TimeBasedPricingStrategy::class,
        ],
        'custom_strategies' => [
            // Add your custom strategies here
            // 'group' => \App\Services\PricingStrategies\GroupBookingStrategy::class,
        ],
        'time_based' => [
            'round_up' => true,
            'minimum_units' => 1,
        ],
    ],
];
```

## Usage

### Basic Booking

```php
use SolutionForest\Bookflow\Models\Booking;
use SolutionForest\Bookflow\Models\Rate;

// Create a rate
$rate = Rate::create([
    'name' => 'Standard Rate',
    'price' => 100,
    'strategy' => 'hour', // 'fixed', 'hour', or 'day'
]);

// Create a booking
$booking = Booking::create([
    'rate_id' => $rate->id,
    'start_time' => now(),
    'end_time' => now()->addHours(2),
    // Additional booking details
]);

// Calculate booking price
$price = $booking->calculatePrice();
```

### Using the HasBookings Trait

Add booking capabilities to your models:

```php
use SolutionForest\Bookflow\Traits\HasBookings;

class Room extends Model
{
    use HasBookings;

    // Your model implementation
}

// Usage
$room = Room::find(1);
$bookings = $room->bookings;
```

### Custom Pricing Strategy

Create a custom pricing strategy:

```php
use SolutionForest\Bookflow\Services\PricingStrategies\PricingStrategy;

class GroupBookingStrategy implements PricingStrategy
{
    public function calculate(Booking $booking): float
    {
        // Your custom pricing logic
        return $booking->rate->price * $booking->group_size * 0.9; // 10% group discount
    }
}
```

Register your custom strategy in `config/bookflow.php`:

```php
'custom_strategies' => [
    'group' => \App\Services\PricingStrategies\GroupBookingStrategy::class,
],
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [alan](https://github.com/lam0819)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
