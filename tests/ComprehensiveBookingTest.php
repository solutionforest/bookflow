<?php

namespace SolutionForest\Bookflow\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon as DateTime;
use SolutionForest\Bookflow\Exceptions\BookingException;
use SolutionForest\Bookflow\Models\Booking;
use SolutionForest\Bookflow\Models\Rate;

class ComprehensiveBookingTest extends TestCase
{
    protected TestResource $resource;

    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resource = new TestResource;
        $this->resource->save();

        $this->customer = new class extends Model
        {
            protected $table = 'customers';

            protected $guarded = [];

            protected $fillable = ['*'];

            public $id;

            public function save(array $options = [])
            {
                if (! $this->exists) {
                    $this->id = 1;
                    $this->exists = true;
                }

                return true;
            }
        };

        $this->customer->save();
    }

    /**
     * Common Booking Scenarios
     */
    public function test_can_create_single_hour_booking()
    {
        $rate = Rate::create([
            'name' => 'Hourly Rate',
            'price' => 50.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'minimum_units' => 1,
            'maximum_units' => 8,
        ]);

        $booking = Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => 1,
            'customer_type' => get_class($this->customer),
            'customer_id' => 1,
            'rate_id' => $rate->id,
            'starts_at' => new DateTime('2024-01-01 10:00:00'),
            'ends_at' => new DateTime('2024-01-01 11:00:00'),
            'price' => 50.00,
            'quantity' => 1,
            'total' => 50.00,
            'status' => 'confirmed',
        ]);

        expect($booking->exists)->toBeTrue()
            ->and($booking->total)->toEqual(50.00);
    }

    public function test_can_create_full_day_booking()
    {
        $rate = Rate::create([
            'name' => 'Day Rate',
            'price' => 400.00,
            'unit' => 'day',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'minimum_units' => 1,
            'maximum_units' => 1,
        ]);

        $booking = Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => 1,
            'customer_type' => get_class($this->customer),
            'customer_id' => 1,
            'rate_id' => $rate->id,
            'starts_at' => new DateTime('2024-01-01 09:00:00'),
            'ends_at' => new DateTime('2024-01-01 17:00:00'),
            'price' => 400.00,
            'quantity' => 1,
            'total' => 400.00,
            'status' => 'confirmed',
        ]);

        expect($booking->exists)->toBeTrue()
            ->and($booking->total)->toEqual(400.00);
    }

    public function test_can_create_multiple_day_booking()
    {
        $rate = Rate::create([
            'name' => 'Weekly Rate',
            'price' => 350.00,
            'unit' => 'day',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'minimum_units' => 1,
            'maximum_units' => 5,
        ]);

        $booking = Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => 1,
            'customer_type' => get_class($this->customer),
            'customer_id' => 1,
            'rate_id' => $rate->id,
            'starts_at' => new DateTime('2024-01-01 09:00:00'),
            'ends_at' => new DateTime('2024-01-05 17:00:00'),
            'price' => 350.00,
            'quantity' => 5,
            'total' => 1750.00,
            'status' => 'confirmed',
        ]);

        expect($booking->exists)->toBeTrue()
            ->and($booking->total)->toEqual(1750.00);
    }

    /**
     * Special Cases and Edge Scenarios
     */
    public function test_cannot_book_outside_rate_time_range()
    {
        $rate = Rate::create([
            'name' => 'Standard Rate',
            'price' => 100.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'minimum_units' => 1,
            'maximum_units' => 8,
        ]);

        expect(fn () => Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => 1,
            'customer_type' => get_class($this->customer),
            'customer_id' => 1,
            'rate_id' => $rate->id,
            'starts_at' => new DateTime('2024-01-01 08:00:00'), // Before opening
            'ends_at' => new DateTime('2024-01-01 10:00:00'),
            'price' => 100.00,
            'quantity' => 2,
            'total' => 200.00,
            'status' => 'confirmed',
        ]))->toThrow(BookingException::class);
    }

    public function test_cannot_book_on_weekend_for_weekday_rate()
    {
        $rate = Rate::create([
            'name' => 'Weekday Rate',
            'price' => 100.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5], // Monday to Friday
            'minimum_units' => 1,
            'maximum_units' => 8,
        ]);

        expect(fn () => Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => 1,
            'customer_type' => get_class($this->customer),
            'customer_id' => 1,
            'rate_id' => $rate->id,
            'starts_at' => new DateTime('2024-01-06 10:00:00'), // Saturday
            'ends_at' => new DateTime('2024-01-06 12:00:00'),
            'price' => 100.00,
            'quantity' => 2,
            'total' => 200.00,
            'status' => 'confirmed',
        ]))->toThrow(BookingException::class);
    }

    public function test_cannot_exceed_maximum_booking_units()
    {
        $rate = Rate::create([
            'name' => 'Standard Rate',
            'price' => 100.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'minimum_units' => 1,
            'maximum_units' => 4,
        ]);

        expect(fn () => Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => 1,
            'customer_type' => get_class($this->customer),
            'customer_id' => 1,
            'rate_id' => $rate->id,
            'starts_at' => new DateTime('2024-01-01 09:00:00'),
            'ends_at' => new DateTime('2024-01-01 15:00:00'), // 6 hours
            'price' => 100.00,
            'quantity' => 6,
            'total' => 600.00,
            'status' => 'confirmed',
        ]))->toThrow(BookingException::class);
    }

    public function test_cannot_create_overlapping_bookings()
    {
        $rate = Rate::create([
            'name' => 'Standard Rate',
            'price' => 100.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'minimum_units' => 1,
            'maximum_units' => 8,
        ]);

        // Create first booking
        Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => 1,
            'customer_type' => get_class($this->customer),
            'customer_id' => 1,
            'rate_id' => $rate->id,
            'starts_at' => new DateTime('2024-01-01 10:00:00'),
            'ends_at' => new DateTime('2024-01-01 12:00:00'),
            'price' => 100.00,
            'quantity' => 2,
            'total' => 200.00,
            'status' => 'confirmed',
        ]);

        // Try to create overlapping booking
        expect(fn () => Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => 1,
            'customer_type' => get_class($this->customer),
            'customer_id' => 1,
            'rate_id' => $rate->id,
            'starts_at' => new DateTime('2024-01-01 11:00:00'),
            'ends_at' => new DateTime('2024-01-01 13:00:00'),
            'price' => 100.00,
            'quantity' => 2,
            'total' => 200.00,
            'status' => 'confirmed',
        ]))->toThrow(BookingException::class);
    }

    public function test_cannot_create_booking_with_invalid_time_range()
    {
        $rate = Rate::create([
            'name' => 'Standard Rate',
            'price' => 100.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'minimum_units' => 1,
            'maximum_units' => 8,
        ]);

        expect(fn () => Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => 1,
            'customer_type' => get_class($this->customer),
            'customer_id' => 1,
            'rate_id' => $rate->id,
            'starts_at' => new DateTime('2024-01-01 12:00:00'),
            'ends_at' => new DateTime('2024-01-01 10:00:00'), // End time before start time
            'price' => 100.00,
            'quantity' => 2,
            'total' => 200.00,
            'status' => 'confirmed',
        ]))->toThrow(BookingException::class);
    }

    public function test_cannot_create_booking_with_zero_duration()
    {
        $rate = Rate::create([
            'name' => 'Standard Rate',
            'price' => 100.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'minimum_units' => 1,
            'maximum_units' => 8,
        ]);

        expect(fn () => Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => 1,
            'customer_type' => get_class($this->customer),
            'customer_id' => 1,
            'rate_id' => $rate->id,
            'starts_at' => new DateTime('2024-01-01 10:00:00'),
            'ends_at' => new DateTime('2024-01-01 10:00:00'),
            'price' => 100.00,
            'quantity' => 0,
            'total' => 0.00,
            'status' => 'confirmed',
        ]))->toThrow(BookingException::class);
    }

    public function test_cannot_create_booking_with_invalid_rate_service_type()
    {
        $rate = Rate::create([
            'name' => 'Premium Rate',
            'price' => 150.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'minimum_units' => 1,
            'maximum_units' => 8,
            'service_type' => 'premium',
        ]);

        expect(fn () => Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => 1,
            'customer_type' => get_class($this->customer),
            'customer_id' => 1,
            'rate_id' => $rate->id,
            'starts_at' => new DateTime('2024-01-01 10:00:00'),
            'ends_at' => new DateTime('2024-01-01 12:00:00'),
            'price' => 150.00,
            'quantity' => 2,
            'total' => 300.00,
            'status' => 'confirmed',
            'service_type' => 'standard', // Mismatch with rate's premium service type
        ]))->toThrow(BookingException::class);
    }

    public function test_cannot_create_booking_with_conflicting_rates()
    {
        // Create two rates for the same time period
        $rate1 = Rate::create([
            'name' => 'Standard Rate',
            'price' => 100.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'minimum_units' => 1,
            'maximum_units' => 8,
        ]);

        $rate2 = Rate::create([
            'name' => 'Premium Rate',
            'price' => 150.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'minimum_units' => 1,
            'maximum_units' => 8,
        ]);

        // Create first booking with rate1
        Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => 1,
            'customer_type' => get_class($this->customer),
            'customer_id' => 1,
            'rate_id' => $rate1->id,
            'starts_at' => new DateTime('2024-01-01 10:00:00'),
            'ends_at' => new DateTime('2024-01-01 12:00:00'),
            'price' => 100.00,
            'quantity' => 2,
            'total' => 200.00,
            'status' => 'confirmed',
        ]);

        // Try to create second booking with rate2 for same time period
        expect(fn () => Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => 1,
            'customer_type' => get_class($this->customer),
            'customer_id' => 1,
            'rate_id' => $rate2->id,
            'starts_at' => new DateTime('2024-01-01 10:00:00'),
            'ends_at' => new DateTime('2024-01-01 12:00:00'),
            'price' => 150.00,
            'quantity' => 2,
            'total' => 300.00,
            'status' => 'confirmed',
        ]))->toThrow(BookingException::class);
    }
}
