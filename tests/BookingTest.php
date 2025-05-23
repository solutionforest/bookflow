<?php

namespace SolutionForest\Bookflow\Tests;

use DateTime;
use SolutionForest\Bookflow\Models\Booking;
use SolutionForest\Bookflow\Models\Rate;

class BookingTest extends TestCase
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

    public function test_can_get_service_types()
    {
        $rate = Rate::create([
            'name' => 'Standard Rate',
            'price' => 100.00,
            'unit' => 'hour',
            'service_type' => 'standard',
        ]);

        expect($rate->service_type)->toBe('standard');
    }

    public function test_can_create_a_booking()
    {
        $rate = Rate::create([
            'name' => 'Standard Rate',
            'price' => 100.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
        ]);

        $booking = Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->customer),
            'customer_id' => $this->customer->id,
            'rate_id' => $rate->id,
            'starts_at' => new DateTime('2024-01-01 10:00:00'),
            'ends_at' => new DateTime('2024-01-01 11:00:00'),
            'price' => 100.00,
            'quantity' => 1,
            'total' => 100.00,
            'status' => 'confirmed',
        ]);

        expect($booking->exists)->toBeTrue();
    }

    public function test_can_get_available_rates_for_datetime()
    {
        $rate = Rate::create([
            'name' => 'Standard Rate',
            'price' => 100.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
        ]);

        $dateTime = new DateTime('2024-01-01 10:00:00'); // Monday at 10 AM
        expect($rate->isAvailableForDateTime($dateTime))->toBeTrue();
    }

    public function test_can_check_resource_availability()
    {
        $rate = Rate::create([
            'name' => 'Standard Rate',
            'price' => 100.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
        ]);

        $booking = new Booking([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => $this->resource->id,
            'customer_type' => get_class($this->customer),
            'customer_id' => $this->customer->id,
            'rate_id' => $rate->id,
            'starts_at' => new DateTime('2024-01-01 10:00:00'),
            'ends_at' => new DateTime('2024-01-01 11:00:00'),
            'price' => 100.00,
            'quantity' => 1,
            'total' => 100.00,
            'status' => 'confirmed',
        ]);

        expect($this->resource->isAvailable($booking->starts_at, $booking->ends_at))->toBeTrue();
    }
}
