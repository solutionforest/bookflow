<?php

namespace SolutionForest\Bookflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $rate_id
 * @property int $day_of_week
 * @property string $starts_at
 * @property string $ends_at
 * @property float $price_modifier
 * @property string|null $description
 */
class CustomPrice extends Model
{
    protected $table = 'bookflow_custom_prices';

    protected $fillable = [
        'rate_id',
        'day_of_week',
        'starts_at',
        'ends_at',
        'price_modifier',
        'description',
    ];

    protected $casts = [
        'price_modifier' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function rate(): BelongsTo
    {
        return $this->belongsTo(Rate::class);
    }

    /**
     * Check if this custom price applies to a given datetime
     */
    public function appliesTo(\DateTime $datetime): bool
    {
        $dayMatches = $datetime->format('w') == $this->day_of_week;

        $currentTime = $datetime->format('H:i:s');
        $startTime = \Carbon\Carbon::parse($this->starts_at)->format('H:i:s');
        $endTime = \Carbon\Carbon::parse($this->ends_at)->format('H:i:s');

        // Handle overnight ranges (when end time is less than start time)
        if ($startTime > $endTime) {
            $timeInRange = $currentTime >= $startTime || $currentTime <= $endTime;
        } else {
            $timeInRange = $currentTime >= $startTime && $currentTime <= $endTime;
        }

        return $dayMatches && $timeInRange;
    }

    /**
     * Apply the price modifier to a base price
     */
    public function applyTo(float $basePrice): float
    {
        return $basePrice * (1 + ($this->price_modifier / 100));
    }
}
