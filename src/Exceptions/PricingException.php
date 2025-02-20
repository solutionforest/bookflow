<?php

namespace SolutionForest\Bookflow\Exceptions;

class PricingException extends \Exception
{
    public static function invalidPriceConfiguration(): self
    {
        return new self('Invalid price configuration.');
    }

    public static function invalidCalculationUnit(): self
    {
        return new self('Invalid calculation unit specified.');
    }

    public static function invalidTimeRange(): self
    {
        return new self('Invalid time range for price calculation.');
    }

    public static function minimumUnitsNotMet(): self
    {
        return new self('Booking duration does not meet minimum units requirement.');
    }

    public static function maximumUnitsExceeded(): self
    {
        return new self('Booking duration exceeds maximum units allowed.');
    }
}
