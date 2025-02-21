# BookFlow

[![Latest Version on Packagist](https://img.shields.io/packagist/v/solutionforest/bookflow.svg?style=flat-square)](https://packagist.org/packages/solutionforest/bookflow)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/solutionforest/bookflow/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/solutionforest/bookflow/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/solutionforest/bookflow/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/solutionforest/bookflow/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/solutionforest/bookflow.svg?style=flat-square)](https://packagist.org/packages/solutionforest/bookflow)

BookFlow is a flexible Laravel package for managing bookings and pricing strategies. It provides a robust foundation for implementing booking systems with customizable pricing calculations.

> ⚠️ **WARNING: DEVELOPMENT STATUS**⚠️ 
> 
> This package is currently under active development and is **NOT READY FOR PRODUCTION USE**. 
> 
> Features may be incomplete, APIs might change, and there could be breaking changes. Use at your own risk in development environments only.


## Features

- Easy booking management with support for one-time and recurring bookings
- Flexible pricing strategies (Fixed, Hourly, Daily)
- Customizable time-based pricing with configurable units and rounding
- Extensible architecture for custom pricing strategies
- Built-in conflict detection and availability checking
- Support for multiple service types and rates
- Comprehensive date and time validation
- Laravel Eloquent integration

## Installation

You can install the package via composer:

```bash
composer require solutionforest/bookflow
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
            'round_up' => true, // Whether to round up partial units
            'minimum_units' => 1, // Minimum number of units to charge
        ],
    ],
];
```

## Usage

### Setting Up Your Models

First, add the `HasBookings` trait to your bookable model:

```php
use SolutionForest\Bookflow\Traits\HasBookings;

class Room extends Model
{
    use HasBookings;

    // Your model implementation
}
```

### Managing Rates

Create and manage different pricing rates:

```php
use SolutionForest\Bookflow\Models\Rate;

// Create a fixed-price rate
$fixedRate = Rate::create([
    'name' => 'Standard Rate',
    'price' => 100,
    'strategy' => 'fixed',
]);

// Create an hourly rate
$hourlyRate = Rate::create([
    'name' => 'Hourly Rate',
    'price' => 50,
    'strategy' => 'hour',
    'minimum_units' => 2, // Minimum 2 hours
]);

// Create a daily rate
$dailyRate = Rate::create([
    'name' => 'Daily Rate',
    'price' => 200,
    'strategy' => 'day',
]);
```

### Basic Booking Operations

```php
use SolutionForest\Bookflow\Models\Booking;

// Create a booking
$booking = Booking::create([
    'rate_id' => $hourlyRate->id,
    'start_time' => now(),
    'end_time' => now()->addHours(3),
    'customer_id' => $customer->id,
    'bookable_id' => $room->id,
    'bookable_type' => Room::class,
]);

// Calculate booking price
$price = $booking->calculatePrice();

// Check booking status
$isConfirmed = $booking->isConfirmed();
$isPending = $booking->isPending();
$isCancelled = $booking->isCancelled();

// Update booking status
$booking->confirm();
$booking->cancel();
```

### Checking Availability

```php
// Check if a room is available for a specific time period
$room = Room::find(1);
$isAvailable = $room->isAvailable(
    start: now(),
    end: now()->addHours(2)
);

// Get all available rates for a time period
$availableRates = $room->getAvailableRates(
    start: now(),
    end: now()->addHours(2)
);

// Find available time slots
use SolutionForest\Bookflow\Helpers\BookingHelper;

$timeSlots = BookingHelper::findAvailableTimeSlots(
    bookable: $room,
    date: now()->toDateString(),
    duration: 60 // minutes
);
```

### Recurring Bookings

Create bookings that repeat on specific days:

```php
use SolutionForest\Bookflow\Models\RecurringBooking;

$recurringBooking = RecurringBooking::create([
    'rate_id' => $hourlyRate->id,
    'start_time' => '09:00',
    'end_time' => '10:00',
    'days_of_week' => ['monday', 'wednesday', 'friday'],
    'starts_from' => now(),
    'ends_at' => now()->addMonths(3),
    'bookable_id' => $room->id,
    'bookable_type' => Room::class,
    'customer_id' => $customer->id,
]);

// Get all bookings generated from this recurring booking
$generatedBookings = $recurringBooking->bookings;

// Update recurring booking
$recurringBooking->update([
    'days_of_week' => ['tuesday', 'thursday'],
    'ends_at' => now()->addMonths(6),
]);
```

### Custom Pricing Strategies

Create a custom pricing strategy:

```php
use SolutionForest\Bookflow\Services\PricingStrategies\PricingStrategy;
use SolutionForest\Bookflow\Models\Booking;

class GroupBookingStrategy implements PricingStrategy
{
    public function calculate(Booking $booking): float
    {
        $basePrice = $booking->rate->price;
        $groupSize = $booking->group_size;
        
        // Apply group discount
        if ($groupSize >= 10) {
            return $basePrice * $groupSize * 0.8; // 20% discount
        } elseif ($groupSize >= 5) {
            return $basePrice * $groupSize * 0.9; // 10% discount
        }
        
        return $basePrice * $groupSize;
    }
}
```

Register your custom strategy in `config/bookflow.php`:

```php
'custom_strategies' => [
    'group' => \App\Services\PricingStrategies\GroupBookingStrategy::class,
],
```

Use the custom strategy:

```php
$groupRate = Rate::create([
    'name' => 'Group Rate',
    'price' => 30,
    'strategy' => 'group',
]);

$booking = Booking::create([
    'rate_id' => $groupRate->id,
    'start_time' => now(),
    'end_time' => now()->addHours(2),
    'group_size' => 8,
    // ... other booking details
]);

$price = $booking->calculatePrice(); // Will use GroupBookingStrategy
```

### Additional Custom Pricing Strategy Example

Here's an example of a seasonal pricing strategy that adjusts rates based on peak seasons and special events:

```php
use SolutionForest\Bookflow\Services\PricingStrategies\PricingStrategy;
use SolutionForest\Bookflow\Models\Booking;
use Carbon\Carbon;

class SeasonalPricingStrategy implements PricingStrategy
{
    protected array $peakSeasons = [
        ['start' => '06-15', 'end' => '09-15'], // Summer peak
        ['start' => '12-15', 'end' => '01-15'], // Holiday peak
    ];

    protected array $specialEvents = [
        '12-24' => 2.0,  // Christmas Eve: 100% markup
        '12-31' => 2.5,  // New Year's Eve: 150% markup
    ];

    public function calculate(Booking $booking): float
    {
        $basePrice = $booking->rate->price;
        $bookingDate = Carbon::parse($booking->start_time);
        
        // Check for special event dates
        $eventDate = $bookingDate->format('m-d');
        if (isset($this->specialEvents[$eventDate])) {
            return $basePrice * $this->specialEvents[$eventDate];
        }
        
        // Check for peak seasons
        foreach ($this->peakSeasons as $season) {
            $seasonStart = Carbon::createFromFormat('m-d', $season['start']);
            $seasonEnd = Carbon::createFromFormat('m-d', $season['end']);
            
            if ($bookingDate->between($seasonStart, $seasonEnd)) {
                return $basePrice * 1.5; // 50% markup during peak season
            }
        }
        
        // Regular season price
        return $basePrice;
    }
}
```

Register the seasonal pricing strategy:

```php
'custom_strategies' => [
    'group' => \App\Services\PricingStrategies\GroupBookingStrategy::class,
    'seasonal' => \App\Services\PricingStrategies\SeasonalPricingStrategy::class,
],
```

Use the seasonal pricing strategy:

```php
$seasonalRate = Rate::create([
    'name' => 'Seasonal Rate',
    'price' => 100, // Base price
    'strategy' => 'seasonal',
]);

$booking = Booking::create([
    'rate_id' => $seasonalRate->id,
    'start_time' => '2024-12-31 20:00:00',
    'end_time' => '2025-01-01 02:00:00',
    // ... other booking details
]);

$price = $booking->calculatePrice(); // Will return 250 (base price * 2.5 for New Year's Eve)
```

### Error Handling

BookFlow provides specific exceptions for different scenarios:

```php
use SolutionForest\Bookflow\Exceptions\BookingException;
use SolutionForest\Bookflow\Exceptions\PricingException;

try {
    $booking = Booking::create([
        // ... booking details
    ]);
} catch (BookingException $e) {
    // Handle booking-related errors (conflicts, validation, etc.)
    report($e);
} catch (PricingException $e) {
    // Handle pricing-related errors
    report($e);
}
```

### Command-Line Tools

BookFlow provides a command-line tool for checking system configuration and data integrity:

```bash
# Run all checks
php artisan bookflow:check --all

# Check rate configurations
php artisan bookflow:check --rates

# Check for booking conflicts
php artisan bookflow:check --bookings

# Test pricing calculations
php artisan bookflow:check --pricing
```

This command helps you:
- Validate rate configurations (units, minimum units, etc.)
- Detect booking conflicts and invalid booking periods
- Test pricing calculations for all rates

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
