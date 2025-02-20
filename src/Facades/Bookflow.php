<?php

namespace SolutionForest\Bookflow\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \SolutionForest\Bookflow\Bookflow
 */
class Bookflow extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \SolutionForest\Bookflow\Bookflow::class;
    }
}
