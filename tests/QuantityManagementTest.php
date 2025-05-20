<?php

namespace SolutionForest\Bookflow\Tests;

use DateTime;
use SolutionForest\Bookflow\Helpers\BookingHelper;
use SolutionForest\Bookflow\Models\Booking;
use SolutionForest\Bookflow\Models\Rate;

class QuantityManagementTest extends TestCase
{
    protected TestResource $resource;

    protected $customer;

    protected Rate $rate;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test resource with capacity of 3
        $this->resource = new class extends TestResource
        {
            public $capacity = 3; // Setting capacity to 3 for our test case
        };
        $this->resource->save();

        // Create a test customer
        $this->customer = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'customers';

            protected $guarded = [];

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

    public function test_resource_has_capacity_of_three()
    {
        // Verify the test resource has proper capacity
        expect($this->resource->capacity)->toBe(3);
    }

    public function test_check_availability_with_single_quantity_returns_true()
    {
        // Check availability for a quantity of 1 when no bookings exist
        $start = new DateTime('2025-01-01 10:00:00');
        $end = new DateTime('2025-01-01 11:00:00');

        $isAvailable = BookingHelper::checkAvailability(
            $this->resource,
            $start,
            $end,
            $this->rate,
            1
        );

        expect($isAvailable)->toBeTrue();
    }

    public function test_check_availability_with_multiple_quantity_returns_true_when_under_capacity()
    {
        // Check availability for a quantity of 2 (resource capacity is 3)
        $start = new DateTime('2025-01-01 10:00:00');
        $end = new DateTime('2025-01-01 11:00:00');

        $isAvailable = BookingHelper::checkAvailability(
            $this->resource,
            $start,
            $end,
            $this->rate,
            2
        );

        expect($isAvailable)->toBeTrue();
    }

    public function test_check_availability_with_exceeding_quantity_returns_false()
    {
        // Check availability for a quantity of 4 (resource capacity is 3)
        $start = new DateTime('2025-01-01 10:00:00');
        $end = new DateTime('2025-01-01 11:00:00');

        $isAvailable = BookingHelper::checkAvailability(
            $this->resource,
            $start,
            $end,
            $this->rate,
            4
        );

        expect($isAvailable)->toBeFalse();
    }

    public function test_check_availability_with_existing_bookings_respects_capacity()
    {
        // Create a booking that uses 2 out of 3 capacity
        $start = new DateTime('2025-01-01 10:00:00');
        $end = new DateTime('2025-01-01 11:00:00');

        Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->customer),
            'customer_id' => $this->customer->id,
            'rate_id' => $this->rate->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'price' => 100.00,
            'quantity' => 2, // Using 2 out of 3 capacity
            'total' => 200.00,
            'status' => 'confirmed',
        ]);

        // Check if we can book 1 more unit (should be true)
        $isAvailable1More = BookingHelper::checkAvailability(
            $this->resource,
            $start,
            $end,
            $this->rate,
            1
        );

        // Check if we can book 2 more units (should be false)
        $isAvailable2More = BookingHelper::checkAvailability(
            $this->resource,
            $start,
            $end,
            $this->rate,
            2
        );

        expect($isAvailable1More)->toBeTrue();
        expect($isAvailable2More)->toBeFalse();
    }

    public function test_find_available_time_slots_returns_empty_when_fully_booked()
    {
        // Create a booking that uses all 3 capacity slots
        $date = new DateTime('2025-01-01'); // Wednesday
        $start = new DateTime('2025-01-01 10:00:00');
        $end = new DateTime('2025-01-01 12:00:00');

        Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->customer),
            'customer_id' => $this->customer->id,
            'rate_id' => $this->rate->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'price' => 200.00,
            'quantity' => 3, // Using all capacity
            'total' => 600.00,
            'status' => 'confirmed',
        ]);

        // When searching for available time slots of 60 minutes during this period,
        // it should find no slots available
        $availableSlots = BookingHelper::findAvailableTimeSlots(
            $this->resource,
            $date,
            60,
            null,
            $this->rate
        );

        // Expecting no available slots for this time period
        $hasSlotFor10to11 = collect($availableSlots)->contains(function ($slot) {
            $slotStart = $slot['start']->format('H:i');

            return $slotStart === '10:00';
        });

        expect($hasSlotFor10to11)->toBeFalse();
    }

    public function test_get_conflicting_bookings_returns_overlapping_bookings()
    {
        // Create a test booking
        $start = new DateTime('2025-01-01 10:00:00');
        $end = new DateTime('2025-01-01 12:00:00');

        $booking = Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->customer),
            'customer_id' => $this->customer->id,
            'rate_id' => $this->rate->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'price' => 200.00,
            'quantity' => 2,
            'total' => 400.00,
            'status' => 'confirmed',
        ]);

        // Check for conflicts with an overlapping time period
        $conflicts = $this->resource->getConflictingBookings(
            new DateTime('2025-01-01 11:00:00'),
            new DateTime('2025-01-01 13:00:00')
        );

        expect($conflicts->count())->toBe(1);
        expect($conflicts->first()->id)->toBe($booking->id);
    }
}
