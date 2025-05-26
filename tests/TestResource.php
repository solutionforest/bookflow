<?php

namespace SolutionForest\Bookflow\Tests;

use Illuminate\Database\Eloquent\Model;
use SolutionForest\Bookflow\Traits\HasBookings;

/**
 * @property int $id
 * @property int $capacity
 */
class TestResource extends Model
{
    use HasBookings;

    protected $table = 'resources';

    protected $guarded = [];

    public $capacity = 3; // Default capacity of 3 bookings per timeslot

    public function save(array $options = [])
    {
        if (! $this->exists) {
            $this->id = 1;
            $this->exists = true;
        }

        return true;
    }
}
