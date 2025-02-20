<?php

namespace SolutionForest\Bookflow\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon as DateTime;
use SolutionForest\Bookflow\Models\Booking;
use SolutionForest\Bookflow\Models\Rate;
use SolutionForest\Bookflow\Tests\TestResource;

class BookingTest extends TestCase
{
    /** @var TestResource */
    protected TestResource $resource;

    /** @var Model */
    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->resource = new TestResource();
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

    public function test_can_create_a_booking()
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
            'resource_type' => get_class($this->resource),
            'resource_id' => 1,
            'service_type' => 'standard',
        ]);

        $booking = Booking::create([
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

        expect($booking->exists)->toBeTrue()
            ->and($booking->status)->toBe('confirmed')
            ->and($booking->total)->toEqual(200.00);
    }

    public function test_can_check_resource_availability()
    {
        $start = new DateTime('2024-01-01 10:00:00');
        $end = new DateTime('2024-01-01 12:00:00');

        expect($this->resource->isAvailable($start, $end))->toBeTrue();

        Booking::create([
            'bookable_type' => get_class($this->resource),
            'bookable_id' => 1,
            'customer_type' => get_class($this->customer),
            'customer_id' => 1,
            'starts_at' => $start,
            'ends_at' => $end,
            'price' => 100.00,
            'quantity' => 2,
            'total' => 200.00,
            'status' => 'confirmed',
        ]);

        expect($this->resource->isAvailable($start, $end))->toBeFalse();
    }

    public function test_can_get_available_rates_for_datetime()
    {
        $rate1 = Rate::create([
            'name' => 'Day Rate',
            'price' => 100.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'minimum_units' => 1,
            'maximum_units' => 8,
            'resource_type' => get_class($this->resource),
            'resource_id' => 1,
            'service_type' => 'standard',
        ]);

        $rate2 = Rate::create([
            'name' => 'Night Rate',
            'price' => 150.00,
            'unit' => 'hour',
            'starts_at' => '17:00',
            'ends_at' => '23:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'minimum_units' => 1,
            'maximum_units' => 6,
            'resource_type' => get_class($this->resource),
            'resource_id' => 1,
            'service_type' => 'premium',
        ]);

        $dateTime = new DateTime('2024-01-01 10:00:00'); // Monday at 10 AM
        $availableRates = $this->resource->getAvailableRates($dateTime);

        expect($availableRates)->toHaveCount(1)
            ->and($availableRates[0]->id)->toBe($rate1->id);

        $dateTime = new DateTime('2024-01-01 20:00:00'); // Monday at 8 PM
        $availableRates = $this->resource->getAvailableRates($dateTime);

        expect($availableRates)->toHaveCount(1)
            ->and($availableRates[0]->id)->toBe($rate2->id);

        // Test with service type filter
        $availableRates = $this->resource->getAvailableRates($dateTime, 'standard');
        expect($availableRates)->toBeEmpty();

        $availableRates = $this->resource->getAvailableRates($dateTime, 'premium');
        expect($availableRates)->toHaveCount(1)
            ->and($availableRates[0]->id)->toBe($rate2->id);
    }

    public function test_can_get_service_types()
    {
        Rate::create([
            'name' => 'Standard Rate',
            'price' => 100.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'minimum_units' => 1,
            'maximum_units' => 8,
            'resource_type' => get_class($this->resource),
            'resource_id' => 1,
            'service_type' => 'standard',
        ]);

        Rate::create([
            'name' => 'Premium Rate',
            'price' => 150.00,
            'unit' => 'hour',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'minimum_units' => 1,
            'maximum_units' => 8,
            'resource_type' => get_class($this->resource),
            'resource_id' => 1,
            'service_type' => 'premium',
        ]);

        $serviceTypes = $this->resource->getServiceTypes();

        expect($serviceTypes)->toBe(['standard', 'premium']);
    }
}
