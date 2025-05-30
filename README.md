# BookFlow

[![Latest Version on Packagist](https://img.shields.io/packagist/v/solution-forest/bookflow.svg?style=flat-square)](https://packagist.org/packages/solution-forest/bookflow)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/solutionforest/bookflow/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/solutionforest/bookflow/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/solutionforest/bookflow/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/solutionforest/bookflow/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/solution-forest/bookflow.svg?style=flat-square)](https://packagist.org/packages/solution-forest/bookflow)

BookFlow is a flexible Laravel package for managing bookings and pricing strategies. It provides a robust foundation for implementing booking systems with customizable pricing calculations.

> ⚠️ **WARNING: DEVELOPMENT STATUS**⚠️ 
> 
> This package is currently under active development and is **NOT READY FOR PRODUCTION USE**. 
> 
> Features may be incomplete, APIs might change, and there could be breaking changes. Use at your own risk in development environments only.


## Features

- Easy booking management with support for one-time and recurring bookings
- **Capacity Management** - Allow multiple bookings per timeslot (default: 1 booking)
- Flexible pricing strategies (Fixed, Hourly, Daily)
- Customizable time-based pricing with configurable units and rounding
- Extensible architecture for custom pricing strategies
- Built-in conflict detection and availability checking with capacity constraints
- Support for multiple service types and rates
- Comprehensive date and time validation
- Laravel Eloquent integration

## Capacity Management

BookFlow allows multiple bookings for the same timeslot when capacity is available.

```php
// Set capacity for your model
class Room extends Model
{
    use HasBookings;
    
    public $capacity = 3; // Allow 3 bookings per timeslot
}

// Multiple users can book the same time
$booking1 = Booking::create([...]);  // Uses 1/3 capacity
$booking2 = Booking::create([...]);  // Uses 2/3 capacity  
$booking3 = Booking::create([...]);  // Uses 3/3 capacity
// 4th booking would throw BookingException

// Check availability
$available = $room->isAvailable($start, $end, null, $quantity);
```

**Default capacity is 1 booking per timeslot.** You can change this in your model or globally in `config/bookflow.php`:

```php
// In config/bookflow.php
'booking' => [
    'default_capacity' => 3, // Change default capacity for all models
]
```

## Installation

> **Requires [PHP 8.3+](https://php.net/releases/), and [Laravel 11.0+](https://laravel.com)**.

You can install the package via composer:

```bash
composer require solution-forest/bookflow
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

BookFlow provides multiple ways to create bookings. Here's the recommended fluent interface:

```php
use SolutionForest\Bookflow\Models\Booking;

// Create a booking using the fluent interface
$booking = Booking::make()
    ->forRate($hourlyRate)
    ->from(now())
    ->to(now()->addHours(3))
    ->forCustomer($customer)
    ->forBookable($room)
    ->withQuantity(2)
    ->withNotes('Special requirements')
    ->save();

// Alternative method using create
$booking = Booking::create([
    'rate_id' => $hourlyRate->id,
    'starts_at' => now(),
    'ends_at' => now()->addHours(3),
    'customer_id' => $customer->id,
    'customer_type' => get_class($customer),
    'bookable_id' => $room->id,
    'bookable_type' => Room::class,
    'quantity' => 1,
]);

// Check booking status
$isPast = $booking->isPast();
$isCurrent = $booking->isCurrent();
$isFuture = $booking->isFuture();
$isCancelled = $booking->isCancelled();

// Get related bookings
$pastBookings = $booking->past();
$currentBookings = $booking->current();
$futureBookings = $booking->future();
$cancelledBookings = $booking->cancelled();
```

### Checking Availability

```php
use SolutionForest\Bookflow\Helpers\BookingHelper;

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
$timeSlots = BookingHelper::findAvailableTimeSlots(
    bookable: $room,
    date: now()->toDateString(),
    duration: 60, // minutes
    rate: $hourlyRate // optional: filter by specific rate
);

// Advanced availability checking
$availability = BookingHelper::checkAvailability(
    bookable: $room,
    start: now(),
    end: now()->addDays(7),
    rate: $hourlyRate,
    quantity: 2 // check if multiple units are available
);

// Get conflicting bookings
$conflicts = $room->getConflictingBookings(
    start: now(),
    end: now()->addHours(2)
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
