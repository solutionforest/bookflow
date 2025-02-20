<?php

namespace SolutionForest\Bookflow\Services\PricingStrategies;

use DateTime;

interface PricingStrategy
{
    public function calculate(DateTime $startTime, DateTime $endTime, float $price, ?int $minimumUnits = null, ?int $maximumUnits = null): float;
}
