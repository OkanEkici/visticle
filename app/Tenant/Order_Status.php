<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Order_Status extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['key', 'description'];

    public function orders(){
        return $this->hasMany(Order::class, 'fk_order_status_id');
    }

    public function getStatusType() {
        $badgeType = 'primary';
        $greenStatus = ["9", "10"];
        $infoStatus = ["1"];
        $warningStatus = ["2", "3", "4", "7", "8"];
        $dangerStatus = ["5", "6"];
        if(in_array($this->id, $greenStatus)) {
            $badgeType = 'success';
        }
        else if(in_array($this->id, $infoStatus)) {
            $badgeType = 'info';
        }
        else if(in_array($this->id, $warningStatus)) {
            $badgeType = 'warning';
        }
        else if(in_array($this->id, $dangerStatus)) {
            $badgeType = 'danger';
        }
    
        return $badgeType;
    }

    public static function getByKey($key) {
        return Order_Status::where('key', '=', $key)->first();
    }

    public static function getIdByKey($key) {
        $status = Order_Status::where('key', '=', $key)->first();
        return ($status) ? $status->id : null;
    }
}
