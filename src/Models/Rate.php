<?php

namespace SolutionForest\Bookflow\Models;

use DateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property float $price
 * @property string $unit
 * @property DateTime|null $starts_at
 * @property DateTime|null $ends_at
 * @property int|null $minimum_units
 * @property int|null $maximum_units
 * @property string|null $resource_type
 * @property int|null $resource_id
 * @property string|null $service_type
 */
class Rate extends Model
{
    use HasFactory;

    protected $table = 'bookflow_rates';

    protected $fillable = [
        'name',
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
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'days_of_week' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'minimum_units' => 'integer',
        'maximum_units' => 'integer',
    ];

    public function requiresPricing(): bool
    {
        return $this->price > 0;
    }

    public function isAvailableForDateTime(DateTime $dateTime): bool
    {
        // Check time constraints
        if ($this->starts_at || $this->ends_at) {
            $time = $dateTime->format('H:i:s');
            $startTime = $this->starts_at ? $this->starts_at->format('H:i:s') : '00:00:00';
            $endTime = $this->ends_at ? $this->ends_at->format('H:i:s') : '23:59:59';

            if ($time < $startTime || $time > $endTime) {
                return false;
            }
        }

        // Check days of week
        $dayOfWeek = $dateTime->format('w');
        $availableDays = $this->days_of_week ?? [0, 1, 2, 3, 4, 5, 6];

        return in_array((int) $dayOfWeek, $availableDays);
    }

    public function calculateUnits(DateTime $startDate, DateTime $endDate): int
    {
        $interval = $startDate->diff($endDate);
        $hours = $interval->h + ($interval->days * 24);

        return max($this->minimum_units ?? 1, $hours);
    }

    public function calculateTotalPrice(int $units): float
    {
        return $this->price * $units;
    }
}
