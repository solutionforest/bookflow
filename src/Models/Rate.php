<?php

namespace SolutionForest\Bookflow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string|null $unit
 * @property float|null $price
 * @property int|null $minimum_units
 * @property int|null $maximum_units
 * @property string $name
 * @property string|null $description
 * @property \DateTime|null $starts_at
 * @property \DateTime|null $ends_at
 * @property array $days_of_week
 * @property string|null $resource_type
 * @property int|null $resource_id
 * @property string|null $service_type
 * @property int|null $duration_minutes
 * @property int|null $break_minutes
 */
class Rate extends Model
{
    use HasFactory;

    protected $table = 'bookflow_rates';

    protected $fillable = [
        'name',
        'description',
        'price',
        'unit',
        'starts_at',
        'ends_at',
        'days_of_week',
        'minimum_units',
        'maximum_units',
        'resource_type',
        'resource_id',
        'service_type',
        'duration_minutes',
        'break_minutes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'minimum_units' => 'integer',
        'maximum_units' => 'integer',
        'days_of_week' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'duration_minutes' => 'integer',
        'break_minutes' => 'integer',
    ];

    protected function setStartsAtAttribute($value)
    {
        if (is_string($value) && ! str_contains($value, ' ')) {
            $value = date('Y-m-d ').$value;
        }
        $this->attributes['starts_at'] = $value;
    }

    protected function setEndsAtAttribute($value)
    {
        if (is_string($value) && ! str_contains($value, ' ')) {
            $value = date('Y-m-d ').$value;
        }
        $this->attributes['ends_at'] = $value;
    }

    protected $attributes = [
        'days_of_week' => '[]',
        'minimum_units' => 1,
    ];

    public function requiresPricing(): bool
    {
        return $this->price !== null;
    }

    public function resource(): MorphTo
    {
        return $this->morphTo();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function isAvailableForDateTime(\DateTime $dateTime): bool
    {
        if (empty($this->days_of_week)) {
            return true;
        }

        $dayOfWeek = (int) $dateTime->format('N');
        if (! in_array($dayOfWeek, $this->days_of_week)) {
            return false;
        }

        if ($this->starts_at && $this->ends_at) {
            $time = $dateTime->format('H:i:s');
            return $time >= $this->starts_at->format('H:i:s') && $time <= $this->ends_at->format('H:i:s');
        }

        return true;
    }

    public function calculateUnits(\DateTime $startTime, \DateTime $endTime): int
    {
        $diffInMinutes = $endTime->diff($startTime)->i + ($endTime->diff($startTime)->h * 60);

        switch ($this->unit) {
            case 'fixed':
                return 1;
            case 'hour':
                return (int) ceil($diffInMinutes / 60);
            case 'day':
                return (int) ceil($diffInMinutes / (24 * 60));
            default:
                return 0;
        }
    }

    /**
     * Calculate the total price based on the number of units.
     *
     * @param int $units The number of units to calculate the price for
     * @return float The total price
     * @throws \InvalidArgumentException If units is not a positive integer
     */
    public function calculateTotalPrice(int $units): float
    {
        if ($units <= 0) {
            throw new \InvalidArgumentException('Units must be a positive integer');
        }

        return (float) ($this->price * $units);
    }
}
