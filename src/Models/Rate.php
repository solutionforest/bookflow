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
        if (is_string($value) && !str_contains($value, ' ')) {
            $value = date('Y-m-d ') . $value;
        }
        $this->attributes['starts_at'] = $value;
    }

    protected function setEndsAtAttribute($value)
    {
        if (is_string($value) && !str_contains($value, ' ')) {
            $value = date('Y-m-d ') . $value;
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
        $timeOfDay = $dateTime->format('H:i:s');
        $startsAt = $this->starts_at ? $this->starts_at->format('H:i:s') : '00:00:00';
        $endsAt = $this->ends_at ? $this->ends_at->format('H:i:s') : '23:59:59';

        return in_array($dayOfWeek, $this->days_of_week)
            && $timeOfDay >= $startsAt
            && $timeOfDay <= $endsAt;
    }

    public function isAvailableForPeriod(\DateTime $start, \DateTime $end): bool
    {
        if (empty($this->days_of_week) || !$this->starts_at || !$this->ends_at) {
            return true;
        }

        $currentDateTime = clone $start;
        while ($currentDateTime <= $end) {
            if (!$this->isAvailableForDateTime($currentDateTime)) {
                return false;
            }
            $currentDateTime->modify('+1 hour');
        }

        return true;
    }
}
