<?php

namespace SolutionForest\Bookflow\Services\PricingStrategies;

use DateTime;

class TimeBasedPricingStrategy implements PricingStrategy
{
    private string $unit;

    public function __construct(string $unit = 'hour')
    {
        $this->unit = $unit;
    }

    public function calculate(DateTime $startTime, DateTime $endTime, float $price, ?int $minimumUnits = null, ?int $maximumUnits = null): float
    {
        $duration = $endTime->getTimestamp() - $startTime->getTimestamp();
        $durationInHours = $duration / 3600;

        $units = $this->unit === 'day' ? ceil($durationInHours / 24) : ceil($durationInHours);

        if ($minimumUnits !== null) {
            $units = max($units, $minimumUnits);
        }

        if ($maximumUnits !== null) {
            $units = min($units, $maximumUnits);
        }

        return $units * $price;
    }
}
