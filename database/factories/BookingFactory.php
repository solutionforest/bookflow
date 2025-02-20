<?php

namespace SolutionForest\Bookflow\Database\Factories;

use DateTime;
use Illuminate\Database\Eloquent\Factories\Factory;
use SolutionForest\Bookflow\Models\Booking;
use SolutionForest\Bookflow\Models\Rate;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition()
    {
        $rate = Rate::factory()->create();
        $startDate = $this->faker->dateTimeBetween('now', '+30 days');
        $endDate = (clone $startDate)->modify('+'.rand(1, 8).' hours');

        return [
            'bookable_type' => $rate->resource_type,
            'bookable_id' => $rate->resource_id,
            'customer_type' => 'App\\Models\\User',
            'customer_id' => 1,
            'rate_id' => $rate->id,
            'starts_at' => $startDate,
            'ends_at' => $endDate,
            'price' => $rate->price,
            'quantity' => $rate->calculateUnits($startDate, $endDate),
            'total' => $rate->calculateTotalPrice($rate->calculateUnits($startDate, $endDate)),
            'status' => 'confirmed',
            'notes' => $this->faker->sentence,
        ];
    }

    public function pending()
    {
        return $this->state(function (array $attributes) {
            return ['status' => 'pending'];
        });
    }

    public function cancelled()
    {
        return $this->state(function (array $attributes) {
            return ['status' => 'cancelled'];
        });
    }

    public function forCustomer($customer)
    {
        return $this->state(function (array $attributes) use ($customer) {
            return [
                'customer_type' => get_class($customer),
                'customer_id' => $customer->id,
            ];
        });
    }

    public function withRate(Rate $rate)
    {
        return $this->state(function (array $attributes) use ($rate) {
            $startDate = new DateTime($attributes['starts_at']);
            $endDate = new DateTime($attributes['ends_at']);

            return [
                'rate_id' => $rate->id,
                'bookable_type' => $rate->resource_type,
                'bookable_id' => $rate->resource_id,
                'price' => $rate->price,
                'quantity' => $rate->calculateUnits($startDate, $endDate),
                'total' => $rate->calculateTotalPrice($rate->calculateUnits($startDate, $endDate)),
            ];
        });
    }
}
