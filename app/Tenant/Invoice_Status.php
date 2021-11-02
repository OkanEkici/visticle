<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Invoice_Status extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['description'];

    public function invoices() {
        return $this->hasMany(Invoice::class,  'fk_invoice_status_id');
    }
}
