<?php

namespace SolutionForest\Bookflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use SolutionForest\Bookflow\Exceptions\BookingException;

/**
 * @property string $bookable_type
 * @property int $bookable_id
 * @property string $customer_type
 * @property int $customer_id
 * @property \DateTime $starts_at
 * @property \DateTime $ends_at
 * @property float $price
 * @property int $quantity
 * @property float $total
 * @property string $status
 * @property string|null $notes
 */
class Booking extends Model
{
    protected $table = 'bookflow_bookings';

    protected $fillable = [
        'bookable_type',
        'bookable_id',
        'customer_type',
        'customer_id',
        'rate_id',
        'starts_at',
        'ends_at',
        'price',
        'quantity',
        'total',
        'status',
        'notes',
        'service_type',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }

    public function customer(): MorphTo
    {
        return $this->morphTo();
    }

    public function rate(): BelongsTo
    {
        return $this->belongsTo(Rate::class);
    }

    protected static function booted()
    {
        static::creating(function ($booking) {
            $booking->load('rate');

            if (! $booking->rate) {
                throw new BookingException('Rate is required for booking');
            }

            // Validate time range
            if ($booking->starts_at >= $booking->ends_at) {
                throw new BookingException('Invalid booking time range');
            }

            // Validate quantity
            if ($booking->quantity < 1) {
                throw new BookingException('Booking quantity must be at least 1');
            }

            // Validate rate time constraints
            if ($booking->rate->starts_at || $booking->rate->ends_at) {
                $startTime = $booking->starts_at->format('H:i:s');
                $endTime = $booking->ends_at->format('H:i:s');
                $rateStartTime = $booking->rate->starts_at ? $booking->rate->starts_at->format('H:i:s') : '00:00:00';
                $rateEndTime = $booking->rate->ends_at ? $booking->rate->ends_at->format('H:i:s') : '23:59:59';

                if ($startTime < $rateStartTime || $endTime > $rateEndTime) {
                    throw new BookingException('Booking time must be within rate time range');
                }
            }

            // Validate days of week
            $bookingDay = $booking->starts_at->dayOfWeek;
            $rateDaysOfWeek = $booking->rate->days_of_week ?? [0, 1, 2, 3, 4, 5, 6];
            if (! in_array($bookingDay, $rateDaysOfWeek)) {
                throw new BookingException('Booking day is not available for this rate');
            }

            // Validate maximum units
            if ($booking->rate->maximum_units && $booking->quantity > $booking->rate->maximum_units) {
                throw new BookingException('Booking exceeds maximum allowed units');
            }

            // Check for overlapping bookings
            $overlapping = static::where('bookable_type', $booking->bookable_type)
                ->where('bookable_id', $booking->bookable_id)
                ->where('status', '!=', 'cancelled')
                ->where(function ($query) use ($booking) {
                    $query->where(function ($q) use ($booking) {
                        $q->where('starts_at', '<=', $booking->ends_at)
                            ->where('ends_at', '>=', $booking->starts_at);
                    });
                })
                ->exists();

            if ($overlapping) {
                throw new BookingException('Booking overlaps with existing booking');
            }

            // Validate service type if specified
            if ($booking->rate->service_type && $booking->service_type &&
                $booking->service_type !== $booking->rate->service_type) {
                throw new BookingException('Invalid service type for this rate');
            }
        });
    }
}
