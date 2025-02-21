<?php

namespace SolutionForest\Bookflow\Tests;

use DateTime;
use SolutionForest\Bookflow\Exceptions\BookingException;
use SolutionForest\Bookflow\Models\Rate;
use SolutionForest\Bookflow\Models\RecurringBooking;

class RecurringBookingTest extends TestCase
{
    protected TestResource $resource;

    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resource = new TestResource;
        $this->resource->save();

        $this->customer = new class extends \Illuminate\Database\Eloquent\Model
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

    public function test_can_create_weekly_recurring_booking()
    {
        $rate = Rate::create([
            'name' => 'Standard Rate',
            'price' => 100.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
        ]);

        $booking = RecurringBooking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->customer),
            'customer_id' => $this->customer->id,
            'rate_id' => $rate->id,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'days_of_week' => [1, 3, 5], // Monday, Wednesday, Friday
            'starts_from' => new DateTime('2024-01-01'),
            'ends_at' => new DateTime('2024-01-31'),
            'price' => 100.00,
            'quantity' => 1,
            'total' => 100.00,
            'status' => 'confirmed',
        ]);

        expect($booking->exists)->toBeTrue();
    }

    public function test_cannot_create_overlapping_recurring_bookings()
    {
        $rate = Rate::create([
            'name' => 'Standard Rate',
            'price' => 100.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
        ]);

        // Create first recurring booking
        RecurringBooking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->customer),
            'customer_id' => $this->customer->id,
            'rate_id' => $rate->id,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'days_of_week' => [1, 3, 5],
            'starts_from' => new DateTime('2024-01-01'),
            'ends_at' => new DateTime('2024-01-31'),
            'price' => 100.00,
            'quantity' => 1,
            'total' => 100.00,
            'status' => 'confirmed',
        ]);

        // Try to create overlapping recurring booking
        expect(fn () => RecurringBooking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->customer),
            'customer_id' => $this->customer->id,
            'rate_id' => $rate->id,
            'start_time' => '10:30',
            'end_time' => '11:30',
            'days_of_week' => [1, 3, 5],
            'starts_from' => new DateTime('2024-01-01'),
            'ends_at' => new DateTime('2024-01-31'),
            'price' => 100.00,
            'quantity' => 1,
            'total' => 100.00,
            'status' => 'confirmed',
        ]))->toThrow(BookingException::class, 'Booking overlaps with existing booking');
    }
}
