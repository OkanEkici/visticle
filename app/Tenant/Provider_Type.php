<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Provider_Type extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['provider_key', 'description', 'name'];

    public function providers() {
        return $this->hasMany(Provider::class, 'fk_provider_type');
    }

    /**
     * Liefert ein query zu einem bestimmten ProviderKey zurück.
     *
     * @param [type] $provier_key
     * @return void
     */
    public static function typesForProviderKey($provier_key){
        return self::query()->where('provider_key',$provier_key);
    }
}
