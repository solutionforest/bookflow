<?php

namespace SolutionForest\Bookflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $bookable_type
 * @property int $bookable_id
 * @property string $customer_type
 * @property int $customer_id
 * @property \DateTime $starts_at
 * @property \DateTime $ends_at
 * @property float $price
 * @property int $quantity
 * @property float $total
 * @property string $status
 * @property string|null $notes
 */
class Booking extends Model
{
    protected $table = 'bookflow_bookings';

    protected $fillable = [
        'bookable_type',
        'bookable_id',
        'customer_type',
        'customer_id',
        'starts_at',
        'ends_at',
        'price',
        'quantity',
        'total',
        'status',
        'notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }

    public function customer(): MorphTo
    {
        return $this->morphTo();
    }

    public function rate(): BelongsTo
    {
        return $this->belongsTo(Rate::class);
    }
}
