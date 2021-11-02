<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class OrderEvents extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['event_id', 'order_id', 'order_number', 'state', 'last_state', 'store_id', 'timestamp', 'items'];
}
