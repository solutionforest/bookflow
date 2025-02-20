<?php

namespace SolutionForest\Bookflow\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SolutionForest\Bookflow\Models\Rate;

class RateFactory extends Factory
{
    protected $model = Rate::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word,
            'description' => $this->faker->sentence,
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'unit' => $this->faker->randomElement(['hour', 'day', 'session']),
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'minimum_units' => 1,
            'maximum_units' => 8,
            'resource_type' => null,
            'resource_id' => null,
        ];
    }

    public function evening()
    {
        return $this->state(function (array $attributes) {
            return [
                'starts_at' => '17:00',
                'ends_at' => '23:59',
                'price' => $attributes['price'] * 1.5,
            ];
        });
    }

    public function weekend()
    {
        return $this->state(function (array $attributes) {
            return [
                'days_of_week' => [6, 7],
                'price' => $attributes['price'] * 2,
            ];
        });
    }

    public function forResource($resource)
    {
        return $this->state(function (array $attributes) use ($resource) {
            return [
                'resource_type' => get_class($resource),
                'resource_id' => $resource->id,
            ];
        });
    }
}
