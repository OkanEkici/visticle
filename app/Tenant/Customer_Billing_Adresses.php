<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Customer_Billing_Adresses extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_customer_id','anrede', 'vorname', 'nachname', 'strasse_nr', 'plz','ort', 'region', 'telefon', 'email', 'firma','steuernummer', 'mobil', 'fax'];

  
    public function customer() {
        return $this->belongsTo(Customer::class, 'fk_customer_id');
    }
    
}
