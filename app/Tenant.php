<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\User;

class Tenant extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
     protected $fillable = [
        'subdomain', 'name', 'db', 'db_user', 'db_pw', 'is_fee_customer','fk_account_type_id ', 'user_id', 'advarics_service_id'
    ];

    public function route($name, $parameters = []) {
        return 'https://' . $this->subdomain . app('url')->route($name, $parameters, false);
    }

    public function getSubdomain() {
        return $this->subdomain;
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function keys() {
        return $this->hasMany(Tenant_Keys::class, 'fk_tenant_id');
    }

    public function type() {
        return $this->belongsTo(AccountType::class, 'fk_account_type_id');
    }

}
