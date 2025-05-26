<?php

namespace SolutionForest\Bookflow\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use SolutionForest\Bookflow\Models\Booking;
use SolutionForest\Bookflow\Models\Rate;

trait HasBookings
{
    public function bookings(): MorphMany
    {
        return $this->morphMany(Booking::class, 'bookable');
    }

    public function rates(): MorphMany
    {
        return $this->morphMany(Rate::class, 'resource');
    }

    public function isAvailable(\DateTime $start, \DateTime $end, ?string $serviceType = null, int $quantity = 1): bool
    {
        if ($start >= $end) {
            return false;
        }

        $query = $this->bookings()
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($q) use ($start, $end) {
                    $q->where('starts_at', '<', $end)
                        ->where('ends_at', '>', $start);
                });
            })
            ->where('status', 'confirmed');

        if ($serviceType) {
            $query->whereHas('rate', function ($q) use ($serviceType) {
                $q->where('service_type', $serviceType);
            });
        }

        $bookedQuantity = $query->where('bookable_type', get_class($this))
            ->where('bookable_id', $this->id)
            ->sum('quantity');

        // Get capacity from this model, default to 3
        $capacity = property_exists($this, 'capacity') ? $this->capacity : 3;

        return ($bookedQuantity + $quantity) <= $capacity;
    }

    public function getAvailableRates(\DateTime $dateTime, ?string $serviceType = null)
    {
        $query = $this->rates();

        if ($serviceType) {
            $query->where('service_type', $serviceType);
        }

        return $query->get()->filter(function (Model $rate) use ($dateTime) {
            return $rate instanceof Rate && $rate->isAvailableForDateTime($dateTime);
        })->values();
    }

    public function isAvailableForDateTime(\DateTime $dateTime): bool
    {
        $rate = $this->rates()->get()->first(function (Model $rate) use ($dateTime) {
            return $rate instanceof Rate && $rate->isAvailableForDateTime($dateTime);
        });

        return $rate !== null;
    }

    public function getConflictingBookings(\DateTime $start, \DateTime $end, ?string $serviceType = null)
    {
        $query = $this->bookings()
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($q) use ($start, $end) {
                    $q->where('starts_at', '<', $end)
                        ->where('ends_at', '>', $start);
                });
            })
            ->where('status', 'confirmed');

        if ($serviceType) {
            $query->whereHas('rate', function ($q) use ($serviceType) {
                $q->where('service_type', $serviceType);
            });
        }

        return $query->get();
    }

    public function getServiceTypes(): array
    {
        return $this->rates()
            ->distinct()
            ->whereNotNull('service_type')
            ->pluck('service_type')
            ->toArray();
    }
}
