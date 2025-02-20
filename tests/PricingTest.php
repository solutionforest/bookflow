<?php

use Illuminate\Support\Carbon as DateTime;
use SolutionForest\Bookflow\Exceptions\PricingException;
use SolutionForest\Bookflow\Models\Rate;
use SolutionForest\Bookflow\Services\PricingCalculator;

test('fixed price calculation works correctly', function () {
    $rate = Rate::create([
        'name' => 'Fixed Rate',
        'price' => 100.00,
        'unit' => 'fixed',
        'minimum_units' => 1,
        'maximum_units' => 1,
    ]);

    $calculator = new PricingCalculator($rate);
    $price = $calculator->calculate(
        new DateTime('2024-01-01 10:00:00'),
        new DateTime('2024-01-01 12:00:00')
    );

    expect($price)->toBe(100.00);
});

test('hourly price calculation works correctly', function () {
    $rate = Rate::create([
        'name' => 'Hourly Rate',
        'price' => 50.00,
        'unit' => 'hour',
        'minimum_units' => 1,
        'maximum_units' => 8,
    ]);

    $calculator = new PricingCalculator($rate);
    $price = $calculator->calculate(
        new DateTime('2024-01-01 10:00:00'),
        new DateTime('2024-01-01 12:00:00')
    );

    expect($price)->toBe(100.00); // 2 hours * $50
});

test('daily price calculation works correctly', function () {
    $rate = Rate::create([
        'name' => 'Daily Rate',
        'price' => 200.00,
        'unit' => 'day',
        'minimum_units' => 1,
        'maximum_units' => 7,
    ]);

    $calculator = new PricingCalculator($rate);
    $price = $calculator->calculate(
        new DateTime('2024-01-01 10:00:00'),
        new DateTime('2024-01-02 09:00:00')
    );

    expect($price)->toBe(200.00); // 1 day * $200
});

test('throws exception for invalid time range', function () {
    $rate = Rate::create([
        'name' => 'Test Rate',
        'price' => 100.00,
        'unit' => 'hour',
    ]);

    $calculator = new PricingCalculator($rate);

    expect(fn () => $calculator->calculate(
        new DateTime('2024-01-01 12:00:00'),
        new DateTime('2024-01-01 10:00:00')
    ))->toThrow(PricingException::class, 'Invalid time range');
});

test('throws exception for invalid price configuration', function () {
    $rate = Rate::create([
        'name' => 'Test Rate',
        'unit' => 'hour',
        'price' => null,
        'minimum_units' => 1,
    ]);

    $calculator = new PricingCalculator($rate);

    expect(fn () => $calculator->calculate(
        new DateTime('2024-01-01 10:00:00'),
        new DateTime('2024-01-01 12:00:00')
    ))->toThrow(PricingException::class);
});

test('throws exception for invalid calculation unit', function () {
    $rate = Rate::create([
        'name' => 'Test Rate',
        'price' => 100.00,
        'unit' => 'invalid',
    ]);

    expect(fn () => new PricingCalculator($rate))
        ->toThrow(PricingException::class, 'Invalid calculation unit');
});

test('respects minimum units constraint', function () {
    $rate = Rate::create([
        'name' => 'Hourly Rate',
        'price' => 50.00,
        'unit' => 'hour',
        'minimum_units' => 2,
        'maximum_units' => 8,
    ]);

    $calculator = new PricingCalculator($rate);
    $price = $calculator->calculate(
        new DateTime('2024-01-01 10:00:00'),
        new DateTime('2024-01-01 11:00:00')
    );

    expect($price)->toBe(100.00); // 1 hour but minimum 2 hours * $50
});

test('respects maximum units constraint', function () {
    $rate = Rate::create([
        'name' => 'Hourly Rate',
        'price' => 50.00,
        'unit' => 'hour',
        'minimum_units' => 1,
        'maximum_units' => 2,
    ]);

    $calculator = new PricingCalculator($rate);
    $price = $calculator->calculate(
        new DateTime('2024-01-01 10:00:00'),
        new DateTime('2024-01-01 13:00:00')
    );

    expect($price)->toBe(100.00); // 3 hours but maximum 2 hours * $50
});
