<?php

namespace SolutionForest\Bookflow\Exceptions;

class BookingException extends \Exception
{
    public static function invalidTimeRange(): self
    {
        return new self('Invalid booking time range provided.');
    }

    public static function resourceUnavailable(): self
    {
        return new self('Resource is not available for the specified time range.');
    }

    public static function invalidServiceType(): self
    {
        return new self('Invalid service type for this resource.');
    }

    public static function invalidStatus(): self
    {
        return new self('Invalid booking status provided.');
    }
}
