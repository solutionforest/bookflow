<?php

namespace SolutionForest\Bookflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SolutionForest\Bookflow\Exceptions\BookingException;

/**
 * @property int $id
 * @property int $rate_id
 * @property string $bookable_type
 * @property int $bookable_id
 * @property string $customer_type
 * @property int $customer_id
 * @property string $start_time
 * @property string $end_time
 * @property array $days_of_week
 * @property \DateTime $starts_from
 * @property \DateTime|null $ends_at
 * @property float $price
 * @property int $quantity
 * @property float $total
 * @property string $status
 * @property string|null $notes
 */
class RecurringBooking extends Model
{
    protected $table = 'bookflow_recurring_bookings';

    protected $fillable = [
        'rate_id',
        'bookable_type',
        'bookable_id',
        'customer_type',
        'customer_id',
        'start_time',
        'end_time',
        'days_of_week',
        'starts_from',
        'ends_at',
        'price',
        'quantity',
        'total',
        'status',
        'notes',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'starts_from' => 'datetime',
        'ends_at' => 'datetime',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function rate(): BelongsTo
    {
        return $this->belongsTo(Rate::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function bookable()
    {
        return $this->morphTo();
    }

    public function customer()
    {
        return $this->morphTo();
    }

    /**
     * Generate individual bookings for this recurring schedule
     */
    public function generateBookings(?\DateTime $until = null): void
    {
        $current = clone $this->starts_from;
        $endDate = $until ?? $this->ends_at;

        while ($current <= $endDate) {
            $dayOfWeek = (int) $current->format('N');

            if (in_array($dayOfWeek, $this->days_of_week)) {
                $startTime = clone $current;
                $startTime->setTime(
                    (int) substr($this->start_time, 0, 2),
                    (int) substr($this->start_time, 3, 2)
                );

                $endTime = clone $current;
                $endTime->setTime(
                    (int) substr($this->end_time, 0, 2),
                    (int) substr($this->end_time, 3, 2)
                );

                $this->bookings()->create([
                    'rate_id' => $this->rate_id,
                    'bookable_type' => $this->bookable_type,
                    'bookable_id' => $this->bookable_id,
                    'customer_type' => $this->customer_type,
                    'customer_id' => $this->customer_id,
                    'starts_at' => $startTime,
                    'ends_at' => $endTime,
                    'price' => $this->price,
                    'quantity' => $this->quantity,
                    'total' => $this->total,
                    'status' => $this->status,
                    'notes' => $this->notes,
                ]);
            }

            $current->modify('+1 day');
        }
    }

    protected static function booted()
    {
        static::creating(function ($recurringBooking) {
            $overlappingBooking = static::query()
                ->where('bookable_type', $recurringBooking->bookable_type)
                ->where('bookable_id', $recurringBooking->bookable_id)
                ->where(function ($query) use ($recurringBooking) {
                    $query->whereRaw('EXISTS (SELECT 1 FROM json_each(days_of_week) WHERE value IN ('.implode(',', $recurringBooking->days_of_week).'))')
                        ->where(function ($query) use ($recurringBooking) {
                            $query->where(function ($query) use ($recurringBooking) {
                                $query->where('start_time', '<', $recurringBooking->end_time)
                                    ->where('end_time', '>', $recurringBooking->start_time);
                            })
                                ->where(function ($query) use ($recurringBooking) {
                                    $query->where('starts_from', '<=', $recurringBooking->ends_at)
                                        ->where(function ($query) use ($recurringBooking) {
                                            $query->whereNull('ends_at')
                                                ->orWhere('ends_at', '>=', $recurringBooking->starts_from);
                                        });
                                });
                        });
                })
                ->first();

            if ($overlappingBooking) {
                throw new BookingException('Booking overlaps with existing booking');
            }
        });
    }
}
