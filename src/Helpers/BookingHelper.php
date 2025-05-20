<?php

namespace SolutionForest\Bookflow\Helpers;

use DateTime;
use Illuminate\Database\Eloquent\Collection;
use SolutionForest\Bookflow\Models\Booking;
use SolutionForest\Bookflow\Models\Rate;

/**
 * Helper class for managing bookings and availability
 */
class BookingHelper
{
    /**
     * Find and calculate prices for available time slots
     *
     * @param  \Illuminate\Database\Eloquent\Model  $resource  The bookable resource
     * @param  DateTime  $startTime  Start time of the slot
     * @param  DateTime  $endTime  End time of the slot
     * @param  string|null  $serviceType  Optional service type filter
     * @param  string  $pricingStrategy  Optional pricing strategy ('fixed' or 'time-based')
     * @return array Array of available slots with calculated prices
     */
    public static function findPrices(
        \Illuminate\Database\Eloquent\Model $resource,
        DateTime $startTime,
        DateTime $endTime,
        ?string $serviceType = null,
        string $pricingStrategy = 'fixed'
    ): array {
        $rates = static::findAvailableRates($resource, $startTime, $endTime, $serviceType);

        if ($rates->isEmpty()) {
            return [];
        }

        $pricedSlots = [];
        foreach ($rates as $rate) {
            $priceCalculator = new \SolutionForest\Bookflow\Services\PricingCalculator($rate instanceof Rate ? $rate : Rate::find($rate->id));
            // Calculate price using only start and end time
            $price = $priceCalculator->calculate($startTime, $endTime);

            $pricedSlots[] = [
                'start' => clone $startTime,
                'end' => clone $endTime,
                'rate' => $rate,
                'calculated_price' => $price,
            ];
        }

        return $pricedSlots;
    }

    /**
     * Find available rates for a specific time slot
     *
     * @param  \Illuminate\Database\Eloquent\Model  $resource  The bookable resource
     * @param  DateTime  $startTime  Start time of the slot
     * @param  DateTime  $endTime  End time of the slot
     * @param  string|null  $serviceType  Optional service type filter
     * @return Collection Collection of available rates
     */
    public static function findAvailableRates(
        \Illuminate\Database\Eloquent\Model $resource,
        DateTime $startTime,
        DateTime $endTime,
        ?string $serviceType = null
    ): Collection {
        $query = Rate::query()
            ->where('resource_type', get_class($resource))
            ->where('resource_id', $resource->id)
            ->where(function ($query) use ($startTime) {
                $dayOfWeek = (int) $startTime->format('N');
                $timeOfDay = $startTime->format('H:i');

                $query->whereJsonContains('days_of_week', $dayOfWeek)
                    ->where('starts_at', '<=', $timeOfDay)
                    ->where('ends_at', '>=', $timeOfDay);
            });

        if ($serviceType) {
            $query->where('service_type', $serviceType);
        }

        return $query->get();
    }

    /**
     * Check if a resource is available for a specific time period with quantity consideration
     *
     * @param  \Illuminate\Database\Eloquent\Model  $bookable  The bookable resource
     * @param  DateTime  $start  Start time
     * @param  DateTime  $end  End time
     * @param  Rate|null  $rate  Optional specific rate to check
     * @param  int  $quantity  Number of units required
     * @return bool Whether the requested quantity is available
     */
    public static function checkAvailability(
        \Illuminate\Database\Eloquent\Model $bookable,
        DateTime $start,
        DateTime $end,
        ?Rate $rate = null,
        int $quantity = 1
    ): bool {
        // Get existing bookings for this time period
        $query = Booking::query()
            ->where('bookable_type', get_class($bookable))
            ->where('bookable_id', $bookable->id)
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($q) use ($start, $end) {
                    $q->where('starts_at', '<', $end)
                        ->where('ends_at', '>', $start);
                });
            })
            ->where('status', 'confirmed');

        // If a specific rate is provided, filter by that rate
        if ($rate) {
            $query->where('rate_id', $rate->id);
        }

        // Get the total booked quantity for this period
        $bookedQuantity = $query->sum('quantity');

        // Check if our requested quantity plus already booked quantity exceeds capacity
        // If no capacity is defined on the model, default to 1 (single resource)
        $capacity = property_exists($bookable, 'capacity') ? $bookable->capacity : 1;

        return ($bookedQuantity + $quantity) <= $capacity;
    }

    /**
     * Find available time slots for a specific day
     *
     * @param  \Illuminate\Database\Eloquent\Model  $resource  The bookable resource
     * @param  DateTime  $date  The date to check
     * @param  int  $durationMinutes  Duration of the slot in minutes
     * @param  string|null  $serviceType  Optional service type filter
     * @param  Rate|null  $rate  Optional specific rate to check
     * @return array Array of available time slots as [start, end] DateTime pairs
     */
    public static function findAvailableTimeSlots(
        \Illuminate\Database\Eloquent\Model $resource,
        DateTime $date,
        int $durationMinutes,
        ?string $serviceType = null,
        ?Rate $rate = null
    ): array {
        // Get all rates for this day
        $rates = static::findAvailableRates(
            $resource,
            $date,
            (clone $date)->modify('+1 day'),
            $serviceType
        );

        // Filter by specific rate if provided
        if ($rate !== null) {
            $rates = $rates->where('id', $rate->id);
        }

        if ($rates->isEmpty()) {
            return [];
        }

        // Get existing bookings for this day
        $existingBookings = Booking::query()
            ->where('bookable_type', get_class($resource))
            ->where('bookable_id', $resource->id)
            ->whereDate('starts_at', $date->format('Y-m-d'))
            ->where('status', 'confirmed')
            ->orderBy('starts_at')
            ->get();

        $availableSlots = [];

        // Check each rate's time range
        foreach ($rates as $rate) {
            $startTime = (clone $date)->setTime(
                (int) substr($rate->starts_at, 0, 2),
                (int) substr($rate->starts_at, 3, 2)
            );

            $endTime = (clone $date)->setTime(
                (int) substr($rate->ends_at, 0, 2),
                (int) substr($rate->ends_at, 3, 2)
            );

            // Find available slots within this rate's time range
            while ($startTime < $endTime) {
                $slotEnd = (clone $startTime)->modify("+{$durationMinutes} minutes");

                if ($slotEnd > $endTime) {
                    break;
                }

                $isAvailable = true;
                foreach ($existingBookings as $booking) {
                    if (
                        ($startTime >= $booking->starts_at && $startTime < $booking->ends_at) ||
                        ($slotEnd > $booking->starts_at && $slotEnd <= $booking->ends_at)
                    ) {
                        $isAvailable = false;
                        break;
                    }
                }

                if ($isAvailable) {
                    $availableSlots[] = [
                        'start' => clone $startTime,
                        'end' => clone $slotEnd,
                        'rate' => $rate,
                    ];
                }

                $startTime->modify('+30 minutes'); // Move to next slot
            }
        }

        return $availableSlots;
    }
}
