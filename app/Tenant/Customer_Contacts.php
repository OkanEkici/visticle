<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Customer_Contacts extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_customer_id'
    , 'anrede'
    , 'vorname'
    , 'nachname'
    , 'firma'
    , 'geburtstag'
    , 'position'
    , 'telefon'
    , 'email'
    , 'mobil'
    , 'fax'
    ,'text_info'
    ];

  
    public function customer() {
        return $this->belongsTo(Customer::class, 'fk_customer_id');
    }
    
}
