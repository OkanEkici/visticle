<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_order_id', 'fk_invoice_status_id', 'number'];

    public function order() {
        return $this->belongsTo(Order::class, 'fk_order_id');
    }

    public function status() {
        return $this->belongsTo(Invoice_Status::class,'fk_invoice_status_id');
    }

    public function payments() {
        return $this->hasMany(Payment::class, 'fk_invoice_id');
    }

    public function setStatus(int $id) {
        $this->fk_invoice_status_id = $id;
    }
}
