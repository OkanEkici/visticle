<?php

namespace App\Tenant;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Tenant\Settings_Attribute;

class TenantUser extends Authenticatable
{
    use Notifiable;

    protected $connection = 'tenant';

        /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
     protected $fillable = [
        'name', 'email', 'password'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
     protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function config() {
        return $this->hasMany(TenantUser_Config::class, 'fk_tenant_user_id');
    }

    public function getAuthPassword() {
        return $this->password;
    }

    public function teamLogoPath() {
        $settingType = Settings_Type::where('name','=', 'general')->first();
        $setting = Setting::where('fk_settings_type_id','=',$settingType->id);
        $settingAttr = $setting->first()->attributes();
        $logo = $settingAttr->get()->where('name', '=', 'logo')->first();
        return ($logo) ? $logo->value : false;
    }

    public function getTableColumnConfig($table) {
        $configurable = TenantUser::getTableConfigurations();
        $config = $this->config()->where('name','=','columnconfig_'.$table)->first();
        if($config) {
            $columnconfig = unserialize($config->value);
        }
        else {
            $columnconfig = $configurable[$table];
        }
        return $columnconfig;
    }

    public static function getTableConfigurations() {
        return [
            'articles' => ['4' => 0, '6' => 0],
            'article_sparesets' => ['1' => 0],
            'article_equipmentsets' => ['1' => 0],
            'customers' => [],
            'customers_bestellungen' => [],
            'customers_ansprechpartner' => [],
            'customers_prices' => [],
            'customer_conditions' => [],
            'zahlungsbedingungen' => [],
            'sparesets' => [],
            'spareset_spare_articles' => ['1' => 0],
            'spareset_articles' => ['1' => 0],
            'equipmentsets' => [],
            'eqset_eq_articles' => ['1' => 0],
            'eqset_articles' => ['1' => 0],
            'orders' => [],
            'payment_conditions' => [],
            'delivery_notes' => [],
            'order_confirmations' => [],
            'invoices' => [],
            'retours' => ['4' => 0],
            'credit_notes' => [],
            'opos' => [],
            'conflicts' => [],
            'partial_shipments' => [],
            'statistics' => [],
            'brands' => [],
            'pricegroups' => [],
            'pricegroups_articles' => [],
            'pricegroups_categories' => [],
            'pricegroups_customers' => [],
            'article_prices' => [],

        ];
    }

}
