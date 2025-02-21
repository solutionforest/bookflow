<?php

namespace SolutionForest\Bookflow\Services;

use DateTime;
use SolutionForest\Bookflow\Models\Rate;
use SolutionForest\Bookflow\Services\PricingStrategies\PricingStrategy;

class PricingCalculator
{
    protected Rate $rate;

    protected PricingStrategy $strategy;

    public function __construct(Rate $rate)
    {
        $this->rate = $rate;
        if ($this->rate->unit === null) {
            throw \SolutionForest\Bookflow\Exceptions\PricingException::invalidPriceConfiguration();
        }
        $this->strategy = $this->createStrategy();
    }

    protected function createStrategy(): PricingStrategy
    {
        if ($this->rate->unit === null) {
            throw \SolutionForest\Bookflow\Exceptions\PricingException::invalidPriceConfiguration();
        }

        $strategies = array_merge(
            config('bookflow.pricing.strategies', []),
            config('bookflow.pricing.custom_strategies', [])
        );

        if (! isset($strategies[$this->rate->unit])) {
            throw \SolutionForest\Bookflow\Exceptions\PricingException::invalidCalculationUnit();
        }

        $strategyClass = $strategies[$this->rate->unit];

        return new $strategyClass($this->rate->unit === 'day' ? 'day' : 'hour');
    }

    public function calculate(DateTime $startTime, DateTime $endTime): float
    {
        if ($startTime >= $endTime) {
            throw \SolutionForest\Bookflow\Exceptions\PricingException::invalidTimeRange();
        }

        if ($this->rate->unit === null) {
            throw \SolutionForest\Bookflow\Exceptions\PricingException::invalidPriceConfiguration();
        }

        if ($this->rate->price === null) {
            throw \SolutionForest\Bookflow\Exceptions\PricingException::invalidPriceConfiguration();
        }

        if ($this->rate->requiresPricing()) {
            return $this->strategy->calculate(
                $startTime,
                $endTime,
                $this->rate->price,
                $this->rate->minimum_units,
                $this->rate->maximum_units
            );
        }

        return $this->rate->price;
    }
}
