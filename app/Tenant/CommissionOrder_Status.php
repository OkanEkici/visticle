<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class CommissionOrder_Status extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['description'];

    public function commissionorders() {
        return $this->hasMany(CommissionOrder::class, 'fk_commissionorder_status_id');
    }
}
