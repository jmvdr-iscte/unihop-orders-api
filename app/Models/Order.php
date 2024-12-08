<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'orders.orders';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'job_id',
        'email',
        'status',
        'delivery_date',
        'delivery_start_time',
        'delivery_end_time',
        'pickup_address',
        'pickup_name',
        'dropoff_address',
        'dropoff_name',
        'distance',
        'standard_delivery_tip',
        'delivery_style',
        'asap'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'delivery_date' => 'datetime',
        'distance' => 'float',
        'standard_delivery_tip' => 'float',
    ];

    /**
     * Scope a query to only include orders with a specific status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get a formatted delivery date.
     *
     * @return string
     */
    public function getFormattedDeliveryDateAttribute()
    {
        return $this->delivery_date->format('F j, Y, g:i a');
    }

    /**
     * Calculate total cost including tip.
     *
     * @return float
     */
    public function getTotalCostAttribute()
    {
        return $this->standard_delivery_tip;
    }
}
