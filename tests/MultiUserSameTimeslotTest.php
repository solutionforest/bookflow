<?php

namespace SolutionForest\Bookflow\Tests;

use DateTime;
use SolutionForest\Bookflow\Exceptions\BookingException;
use SolutionForest\Bookflow\Models\Booking;
use SolutionForest\Bookflow\Models\Rate;

class MultiUserSameTimeslotTest extends TestCase
{
    protected TestResource $resource;

    protected $userA;

    protected $userB;

    protected $userC;

    protected $userD;

    protected Rate $rate;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test resource with capacity of 3
        $this->resource = new TestResource;
        $this->resource->save();

        // Create test users
        $this->userA = $this->createUser('A', 1);
        $this->userB = $this->createUser('B', 2);
        $this->userC = $this->createUser('C', 3);
        $this->userD = $this->createUser('D', 4);

        // Create a standard rate for testing
        $this->rate = Rate::create([
            'name' => 'Conference Room Rate',
            'price' => 50.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5], // Monday to Friday
        ]);
    }

    private function createUser(string $name, int $id)
    {
        $user = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'users';
            protected $guarded = [];
            public $id;
            public $name;

            public function save(array $options = [])
            {
                $this->exists = true;
                return true;
            }
        };
        
        $user->name = $name;
        $user->id = $id;
        $user->save();
        
        return $user;
    }

    public function test_scenario_two_users_can_book_same_timeslot_when_capacity_not_full()
    {
        // Scenario: Conference room that allows 3 people at the same time
        $meetingStart = new DateTime('2025-05-27 14:00:00'); // Tuesday 2 PM
        $meetingEnd = new DateTime('2025-05-27 15:00:00'); // Tuesday 3 PM

        // User A books the conference room for 14:00-15:00
        $bookingA = Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->userA),
            'customer_id' => $this->userA->id,
            'rate_id' => $this->rate->id,
            'starts_at' => $meetingStart,
            'ends_at' => $meetingEnd,
            'price' => 50.00,
            'quantity' => 1,
            'total' => 50.00,
            'status' => 'confirmed',
        ]);

        // User B also books the same conference room for the same time (should succeed)
        $bookingB = Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->userB),
            'customer_id' => $this->userB->id,
            'rate_id' => $this->rate->id,
            'starts_at' => $meetingStart,
            'ends_at' => $meetingEnd,
            'price' => 50.00,
            'quantity' => 1,
            'total' => 50.00,
            'status' => 'confirmed',
        ]);

        // Verify both bookings were successful
        expect($bookingA->exists)->toBeTrue();
        expect($bookingB->exists)->toBeTrue();

        // Verify they are for the exact same time
        expect($bookingA->starts_at->format('Y-m-d H:i:s'))->toBe($bookingB->starts_at->format('Y-m-d H:i:s'));
        expect($bookingA->ends_at->format('Y-m-d H:i:s'))->toBe($bookingB->ends_at->format('Y-m-d H:i:s'));

        // Check that the resource shows as partially available
        expect($this->resource->isAvailable($meetingStart, $meetingEnd, null, 1))->toBeTrue();
        expect($this->resource->isAvailable($meetingStart, $meetingEnd, null, 2))->toBeFalse();
    }

    public function test_scenario_third_user_can_still_book_when_two_slots_taken()
    {
        $meetingStart = new DateTime('2025-05-27 14:00:00'); // Tuesday 2 PM
        $meetingEnd = new DateTime('2025-05-27 15:00:00'); // Tuesday 3 PM

        // User A and User B have already booked
        foreach ([$this->userA, $this->userB] as $user) {
            Booking::create([
                'bookable_type' => get_class($this->resource),
                'bookable_id' => $this->resource->id,
                'customer_type' => get_class($user),
                'customer_id' => $user->id,
                'rate_id' => $this->rate->id,
                'starts_at' => $meetingStart,
                'ends_at' => $meetingEnd,
                'price' => 50.00,
                'quantity' => 1,
                'total' => 50.00,
                'status' => 'confirmed',
            ]);
        }

        // User C can still book the same timeslot (last available slot)
        $bookingC = Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->userC),
            'customer_id' => $this->userC->id,
            'rate_id' => $this->rate->id,
            'starts_at' => $meetingStart,
            'ends_at' => $meetingEnd,
            'price' => 50.00,
            'quantity' => 1,
            'total' => 50.00,
            'status' => 'confirmed',
        ]);

        expect($bookingC->exists)->toBeTrue();

        // Now the resource should be fully booked
        expect($this->resource->isAvailable($meetingStart, $meetingEnd, null, 1))->toBeFalse();
    }

    public function test_scenario_fourth_user_gets_error_when_capacity_full()
    {
        $meetingStart = new DateTime('2025-05-27 14:00:00'); // Tuesday 2 PM
        $meetingEnd = new DateTime('2025-05-27 15:00:00'); // Tuesday 3 PM

        // Fill all 3 capacity slots
        foreach ([$this->userA, $this->userB, $this->userC] as $user) {
            Booking::create([
                'bookable_type' => get_class($this->resource),
                'bookable_id' => $this->resource->id,
                'customer_type' => get_class($user),
                'customer_id' => $user->id,
                'rate_id' => $this->rate->id,
                'starts_at' => $meetingStart,
                'ends_at' => $meetingEnd,
                'price' => 50.00,
                'quantity' => 1,
                'total' => 50.00,
                'status' => 'confirmed',
            ]);
        }

        // User D tries to book - should get capacity exceeded error
        expect(function () use ($meetingStart, $meetingEnd) {
            Booking::create([
                'bookable_type' => get_class($this->resource),
                'bookable_id' => $this->resource->id,
                'customer_type' => get_class($this->userD),
                'customer_id' => $this->userD->id,
                'rate_id' => $this->rate->id,
                'starts_at' => $meetingStart,
                'ends_at' => $meetingEnd,
                'price' => 50.00,
                'quantity' => 1,
                'total' => 50.00,
                'status' => 'confirmed',
            ]);
        })->toThrow(BookingException::class, 'Booking exceeds capacity. Available: 0, Requested: 1');
    }

    public function test_scenario_mixed_quantities_respect_capacity()
    {
        $meetingStart = new DateTime('2025-05-27 14:00:00'); // Tuesday 2 PM
        $meetingEnd = new DateTime('2025-05-27 15:00:00'); // Tuesday 3 PM

        // User A books 2 slots for a larger group
        $bookingA = Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->userA),
            'customer_id' => $this->userA->id,
            'rate_id' => $this->rate->id,
            'starts_at' => $meetingStart,
            'ends_at' => $meetingEnd,
            'price' => 50.00,
            'quantity' => 2, // Books 2 out of 3 capacity
            'total' => 100.00,
            'status' => 'confirmed',
        ]);

        // User B can book the remaining 1 slot
        $bookingB = Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->userB),
            'customer_id' => $this->userB->id,
            'rate_id' => $this->rate->id,
            'starts_at' => $meetingStart,
            'ends_at' => $meetingEnd,
            'price' => 50.00,
            'quantity' => 1, // Books remaining 1 slot
            'total' => 50.00,
            'status' => 'confirmed',
        ]);

        expect($bookingA->exists)->toBeTrue();
        expect($bookingB->exists)->toBeTrue();

        // User C cannot book even 1 slot as capacity is full
        expect(function () use ($meetingStart, $meetingEnd) {
            Booking::create([
                'bookable_type' => get_class($this->resource),
                'bookable_id' => $this->resource->id,
                'customer_type' => get_class($this->userC),
                'customer_id' => $this->userC->id,
                'rate_id' => $this->rate->id,
                'starts_at' => $meetingStart,
                'ends_at' => $meetingEnd,
                'price' => 50.00,
                'quantity' => 1,
                'total' => 50.00,
                'status' => 'confirmed',
            ]);
        })->toThrow(BookingException::class, 'Booking exceeds capacity');
    }
}
