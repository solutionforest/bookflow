<?php

namespace SolutionForest\Bookflow\Services\PricingStrategies;

use DateTime;

class FixedPriceStrategy implements PricingStrategy
{
    public function calculate(DateTime $startTime, DateTime $endTime, float $price, ?int $minimumUnits = null, ?int $maximumUnits = null): float
    {
        return $price;
    }
}
