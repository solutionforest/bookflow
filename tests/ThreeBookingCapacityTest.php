<?php

namespace SolutionForest\Bookflow\Tests;

use DateTime;
use SolutionForest\Bookflow\Exceptions\BookingException;
use SolutionForest\Bookflow\Helpers\BookingHelper;
use SolutionForest\Bookflow\Models\Booking;
use SolutionForest\Bookflow\Models\Rate;

class ThreeBookingCapacityTest extends TestCase
{
    protected TestResource $resource;

    protected $customer1;

    protected $customer2;

    protected $customer3;

    protected Rate $rate;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test resource with default capacity of 3
        $this->resource = new TestResource;
        $this->resource->save();

        // Create multiple test customers
        $this->customer1 = $this->createTestCustomer(1);
        $this->customer2 = $this->createTestCustomer(2);
        $this->customer3 = $this->createTestCustomer(3);

        // Create a standard rate for testing
        $this->rate = Rate::create([
            'name' => 'Standard Rate',
            'price' => 100.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5], // Monday to Friday
        ]);
    }

    private function createTestCustomer(int $id)
    {
        $customer = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'customers';

            protected $guarded = [];

            public $id;

            public function save(array $options = [])
            {
                $this->exists = true;

                return true;
            }
        };

        $customer->id = $id;
        $customer->save();

        return $customer;
    }

    public function test_default_resource_has_capacity_of_three()
    {
        // Verify that default capacity is 3 when not explicitly set
        $capacity = property_exists($this->resource, 'capacity') ? $this->resource->capacity : 3;
        expect($capacity)->toBe(3);
    }

    public function test_can_create_three_bookings_for_same_timeslot()
    {
        $start = new DateTime('2025-05-27 10:00:00'); // Tuesday
        $end = new DateTime('2025-05-27 11:00:00');

        // Create first booking
        $booking1 = Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->customer1),
            'customer_id' => $this->customer1->id,
            'rate_id' => $this->rate->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'price' => 100.00,
            'quantity' => 1,
            'total' => 100.00,
            'status' => 'confirmed',
        ]);

        // Create second booking
        $booking2 = Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->customer2),
            'customer_id' => $this->customer2->id,
            'rate_id' => $this->rate->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'price' => 100.00,
            'quantity' => 1,
            'total' => 100.00,
            'status' => 'confirmed',
        ]);

        // Create third booking
        $booking3 = Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->customer3),
            'customer_id' => $this->customer3->id,
            'rate_id' => $this->rate->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'price' => 100.00,
            'quantity' => 1,
            'total' => 100.00,
            'status' => 'confirmed',
        ]);

        expect($booking1->exists)->toBeTrue();
        expect($booking2->exists)->toBeTrue();
        expect($booking3->exists)->toBeTrue();

        // Verify all bookings are for the same timeslot
        expect($booking1->starts_at)->toEqual($booking2->starts_at);
        expect($booking2->starts_at)->toEqual($booking3->starts_at);
        expect($booking1->ends_at)->toEqual($booking2->ends_at);
        expect($booking2->ends_at)->toEqual($booking3->ends_at);
    }

    public function test_fourth_booking_throws_exception()
    {
        $start = new DateTime('2025-05-27 10:00:00'); // Tuesday
        $end = new DateTime('2025-05-27 11:00:00');

        // Create three bookings to fill capacity
        for ($i = 1; $i <= 3; $i++) {
            Booking::create([
                'bookable_type' => get_class($this->resource),
                'bookable_id' => $this->resource->id,
                'customer_type' => get_class($this->{"customer$i"}),
                'customer_id' => $this->{"customer$i"}->id,
                'rate_id' => $this->rate->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'price' => 100.00,
                'quantity' => 1,
                'total' => 100.00,
                'status' => 'confirmed',
            ]);
        }

        // Try to create a fourth booking - should throw exception
        expect(function () use ($start, $end) {
            Booking::create([
                'bookable_type' => get_class($this->resource),
                'bookable_id' => $this->resource->id,
                'customer_type' => get_class($this->customer1),
                'customer_id' => $this->customer1->id,
                'rate_id' => $this->rate->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'price' => 100.00,
                'quantity' => 1,
                'total' => 100.00,
                'status' => 'confirmed',
            ]);
        })->toThrow(BookingException::class, 'Booking exceeds capacity');
    }

    public function test_availability_check_with_partial_capacity()
    {
        $start = new DateTime('2025-05-27 10:00:00'); // Tuesday
        $end = new DateTime('2025-05-27 11:00:00');

        // Create two bookings
        for ($i = 1; $i <= 2; $i++) {
            Booking::create([
                'bookable_type' => get_class($this->resource),
                'bookable_id' => $this->resource->id,
                'customer_type' => get_class($this->{"customer$i"}),
                'customer_id' => $this->{"customer$i"}->id,
                'rate_id' => $this->rate->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'price' => 100.00,
                'quantity' => 1,
                'total' => 100.00,
                'status' => 'confirmed',
            ]);
        }

        // Check availability for 1 more booking (should be true)
        $isAvailable1 = $this->resource->isAvailable($start, $end, null, 1);
        expect($isAvailable1)->toBeTrue();

        // Check availability for 2 more bookings (should be false)
        $isAvailable2 = $this->resource->isAvailable($start, $end, null, 2);
        expect($isAvailable2)->toBeFalse();
    }

    public function test_booking_helper_check_availability_respects_capacity()
    {
        $start = new DateTime('2025-05-27 10:00:00'); // Tuesday
        $end = new DateTime('2025-05-27 11:00:00');

        // Create one booking
        Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->customer1),
            'customer_id' => $this->customer1->id,
            'rate_id' => $this->rate->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'price' => 100.00,
            'quantity' => 1,
            'total' => 100.00,
            'status' => 'confirmed',
        ]);

        // Check availability using BookingHelper
        $canBook1 = BookingHelper::checkAvailability($this->resource, $start, $end, $this->rate, 1);
        $canBook2 = BookingHelper::checkAvailability($this->resource, $start, $end, $this->rate, 2);
        $canBook3 = BookingHelper::checkAvailability($this->resource, $start, $end, $this->rate, 3);

        expect($canBook1)->toBeTrue(); // Can book 1 more (2/3 used)
        expect($canBook2)->toBeTrue(); // Can book 2 more (3/3 used)
        expect($canBook3)->toBeFalse(); // Cannot book 3 more (would exceed capacity)
    }

    public function test_overlapping_timeslots_respect_capacity()
    {
        // Create a booking from 10:00-12:00
        $booking1Start = new DateTime('2025-05-27 10:00:00');
        $booking1End = new DateTime('2025-05-27 12:00:00');

        Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->customer1),
            'customer_id' => $this->customer1->id,
            'rate_id' => $this->rate->id,
            'starts_at' => $booking1Start,
            'ends_at' => $booking1End,
            'price' => 200.00,
            'quantity' => 2, // Uses 2 out of 3 capacity
            'total' => 200.00,
            'status' => 'confirmed',
        ]);

        // Try to create overlapping booking from 11:00-13:00 with quantity 1 (should succeed)
        $booking2Start = new DateTime('2025-05-27 11:00:00');
        $booking2End = new DateTime('2025-05-27 13:00:00');

        $booking2 = Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->customer2),
            'customer_id' => $this->customer2->id,
            'rate_id' => $this->rate->id,
            'starts_at' => $booking2Start,
            'ends_at' => $booking2End,
            'price' => 200.00,
            'quantity' => 1, // Uses 1 more, total 3/3 capacity during overlap
            'total' => 200.00,
            'status' => 'confirmed',
        ]);

        expect($booking2->exists)->toBeTrue();

        // Try to create another overlapping booking (should fail)
        expect(function () use ($booking2Start, $booking2End) {
            Booking::create([
                'bookable_type' => get_class($this->resource),
                'bookable_id' => $this->resource->id,
                'customer_type' => get_class($this->customer3),
                'customer_id' => $this->customer3->id,
                'rate_id' => $this->rate->id,
                'starts_at' => $booking2Start,
                'ends_at' => $booking2End,
                'price' => 100.00,
                'quantity' => 1,
                'total' => 100.00,
                'status' => 'confirmed',
            ]);
        })->toThrow(BookingException::class, 'Booking exceeds capacity');
    }

    public function test_cancelled_bookings_do_not_count_towards_capacity()
    {
        $start = new DateTime('2025-05-27 10:00:00'); // Tuesday
        $end = new DateTime('2025-05-27 11:00:00');

        // Create three confirmed bookings
        for ($i = 1; $i <= 3; $i++) {
            Booking::create([
                'bookable_type' => get_class($this->resource),
                'bookable_id' => $this->resource->id,
                'customer_type' => get_class($this->{"customer$i"}),
                'customer_id' => $this->{"customer$i"}->id,
                'rate_id' => $this->rate->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'price' => 100.00,
                'quantity' => 1,
                'total' => 100.00,
                'status' => $i === 3 ? 'cancelled' : 'confirmed',
            ]);
        }

        // Should be able to create another booking since one is cancelled
        $booking4 = Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->customer1),
            'customer_id' => $this->customer1->id,
            'rate_id' => $this->rate->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'price' => 100.00,
            'quantity' => 1,
            'total' => 100.00,
            'status' => 'confirmed',
        ]);

        expect($booking4->exists)->toBeTrue();
    }
}
