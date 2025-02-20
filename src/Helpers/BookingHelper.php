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
     * Find available time slots for a specific day
     *
     * @param  \Illuminate\Database\Eloquent\Model  $resource  The bookable resource
     * @param  DateTime  $date  The date to check
     * @param  int  $durationMinutes  Duration of the slot in minutes
     * @param  string|null  $serviceType  Optional service type filter
     * @return array Array of available time slots as [start, end] DateTime pairs
     */
    public static function findAvailableTimeSlots(
        \Illuminate\Database\Eloquent\Model $resource,
        DateTime $date,
        int $durationMinutes,
        ?string $serviceType = null
    ): array {
        // Get all rates for this day
        $rates = static::findAvailableRates(
            $resource,
            $date,
            (clone $date)->modify('+1 day'),
            $serviceType
        );

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
