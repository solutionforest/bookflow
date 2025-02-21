<?php

namespace SolutionForest\Bookflow\Models;

use DateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
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
        'minimum_units',
        'maximum_units',
        'resource_type',
        'resource_id',
        'service_type',
        'days_of_week',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'minimum_units' => 'integer',
        'maximum_units' => 'integer',
        'days_of_week' => 'array',
    ];

    public function customPrices(): HasMany
    {
        return $this->hasMany(CustomPrice::class);
    }

    /**
     * Calculate the adjusted price for a specific datetime
     */
    public function getPriceForDateTime(DateTime $datetime): float
    {
        $basePrice = $this->price;

        // Find any applicable custom prices
        /** @var \Illuminate\Database\Eloquent\Collection<int, CustomPrice> $customPrices */
        $customPrices = $this->customPrices;
        $customPrice = $customPrices->first(function ($price) use ($datetime) {
            /** @var CustomPrice $price */
            return $price->appliesTo($datetime);
        });

        // Apply custom price modifier if found
        if ($customPrice instanceof CustomPrice) {
            return $customPrice->applyTo($basePrice);
        }

        return $basePrice;
    }

    /**
     * Check if the rate requires pricing calculation
     */
    public function requiresPricing(): bool
    {
        return $this->unit !== 'fixed';
    }

    /**
     * Calculate the number of units between two datetimes based on the rate unit
     */
    public function calculateUnits(DateTime $start, DateTime $end): float
    {
        $diff = $start->diff($end);

        return match ($this->unit) {
            'hour' => ($diff->days * 24) + $diff->h + ($diff->i / 60),
            'day' => $diff->days + ($diff->h > 0 || $diff->i > 0 ? 1 : 0),
            'fixed' => 1,
            default => throw new \InvalidArgumentException("Unsupported unit type: {$this->unit}")
        };
    }

    /**
     * Calculate the total price for a booking period
     */
    public function calculateTotalPrice(DateTime $start, DateTime $end): float
    {
        $units = $this->calculateUnits($start, $end);
        $pricePerUnit = $this->getPriceForDateTime($start);

        return $units * $pricePerUnit;
    }

    /**
     * Check if the rate is available for a specific datetime
     */
    public function isAvailableForDateTime(DateTime $datetime): bool
    {
        // Check if datetime is within rate's time range
        if ($this->starts_at && $this->ends_at) {
            $currentTime = (clone $datetime)->setDate(2000, 1, 1);
            $startTime = (clone $this->starts_at)->setDate(2000, 1, 1);
            $endTime = (clone $this->ends_at)->setDate(2000, 1, 1);

            if ($currentTime < $startTime || $currentTime > $endTime) {
                return false;
            }
        }

        // Check if the day of week is allowed
        if (! empty($this->days_of_week)) {
            $dayOfWeek = (int) $datetime->format('N'); // 1 (Monday) to 7 (Sunday)
            if (! in_array($dayOfWeek, $this->days_of_week)) {
                return false;
            }
        }

        return true;
    }
}
