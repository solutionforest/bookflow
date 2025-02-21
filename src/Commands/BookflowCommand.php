<?php

namespace SolutionForest\Bookflow\Commands;

use Illuminate\Console\Command;
use SolutionForest\Bookflow\Models\Booking;
use SolutionForest\Bookflow\Models\Rate;
use SolutionForest\Bookflow\Services\PricingCalculator;

class BookflowCommand extends Command
{
    protected $signature = 'bookflow:check
        {--rates : Check rate configurations}
        {--bookings : Check booking conflicts}
        {--pricing : Test pricing calculations}
        {--all : Run all checks}';

    protected $description = 'Check BookFlow system configuration and data integrity';

    public function handle(): int
    {
        if ($this->option('all') || $this->option('rates')) {
            $this->checkRates();
        }

        if ($this->option('all') || $this->option('bookings')) {
            $this->checkBookings();
        }

        if ($this->option('all') || $this->option('pricing')) {
            $this->checkPricing();
        }

        $this->info('\nAll checks completed!');

        return self::SUCCESS;
    }

    protected function checkRates(): void
    {
        $this->info('\nChecking rate configurations...');

        $rates = Rate::all();
        if ($rates->isEmpty()) {
            $this->warn('No rates found in the system.');

            return;
        }

        foreach ($rates as $rate) {
            $this->line("\nRate: {$rate->name}");
            $this->line("- Price: {$rate->price}");
            $this->line("- Unit: {$rate->unit}");

            if (! in_array($rate->unit, ['fixed', 'hour', 'day'])) {
                $this->error("Invalid unit type for rate {$rate->name}");
            }

            if ($rate->minimum_units < 1) {
                $this->error("Invalid minimum units for rate {$rate->name}");
            }
        }
    }

    protected function checkBookings(): void
    {
        $this->info('\nChecking bookings...');

        $bookings = Booking::all();
        if ($bookings->isEmpty()) {
            $this->warn('No bookings found in the system.');

            return;
        }

        foreach ($bookings as $booking) {
            $this->line("\nBooking #{$booking->id}");
            $this->line("- Start: {$booking->starts_at->format('Y-m-d H:i:s')}");
            $this->line("- End: {$booking->ends_at->format('Y-m-d H:i:s')}");

            if ($booking->starts_at >= $booking->ends_at) {
                $this->error("Invalid booking period for booking #{$booking->id}");
            }

            // Check for conflicts
            $conflicts = Booking::where('id', '!=', $booking->id)
                ->where('bookable_type', $booking->bookable_type)
                ->where('bookable_id', $booking->bookable_id)
                ->where(function ($query) use ($booking) {
                    $query->whereBetween('starts_at', [$booking->starts_at, $booking->ends_at])
                        ->orWhereBetween('ends_at', [$booking->starts_at, $booking->ends_at]);
                })
                ->get();

            if ($conflicts->isNotEmpty()) {
                $this->error("Booking #{$booking->id} has conflicts with: ".$conflicts->pluck('id')->join(', '));
            }
        }
    }

    protected function checkPricing(): void
    {
        $this->info('\nTesting pricing calculations...');

        $rates = Rate::all();
        if ($rates->isEmpty()) {
            $this->warn('No rates available for pricing tests.');

            return;
        }

        foreach ($rates as $rate) {
            $this->line("\nTesting rate: {$rate->name}");

            // Test basic pricing calculation
            try {
                $calculator = new PricingCalculator($rate);
                $testStart = now();
                $testEnd = match ($rate->unit) {
                    'fixed' => $testStart->addHour(),
                    'hour' => $testStart->addHours(2),
                    'day' => $testStart->addDays(2),
                    default => $testStart->addHour(),
                };

                $price = $calculator->calculate($testStart, $testEnd);
                $this->line("- Test period price: {$price}");

                if ($price <= 0) {
                    $this->error("Invalid price calculation for rate {$rate->name}");
                }
            } catch (\Exception $e) {
                $this->error("Pricing calculation failed for rate {$rate->name}: {$e->getMessage()}");
            }
        }
    }
}
