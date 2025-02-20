<?php

namespace SolutionForest\Bookflow\Tests;

use Illuminate\Database\Eloquent\Model;
use SolutionForest\Bookflow\Traits\HasBookings;

/**
 * @property int $id
 */
class TestResource extends Model
{
    use HasBookings;

    protected $table = 'resources';

    protected $guarded = [];

    public function save(array $options = [])
    {
        if (! $this->exists) {
            $this->id = 1;
            $this->exists = true;
        }

        return true;
    }
}
