<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TenantUserModule extends Model
{
    protected $connection = 'tenant';
}
