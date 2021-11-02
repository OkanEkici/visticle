<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Tenant\Customer_Billing_Adresses;
use App\Tenant\Customer_Shipping_Adresses;
use App\Tenant\Customer_Attribute;
use App\Manager\Content\ContentManager;

class Customer extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'id'
        ,'knr'
        , 'email'
        , 'anrede'
        , 'vorname'
        , 'nachname'
        , 'firma'
        , 'steuernummer'
        , 'telefon'
        , 'mobil'
        , 'fax'
        , 'UStId'
        , 'zusatz_telefon'
        , 'zusatz_email'
        , 'text_info'
        , 'idv_knr'
    ];


    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($customer)use($content_manager) {
                $content_manager->registrateOperation($customer,'insert','scheduled');
            });

            self::updated(function($customer)use($content_manager) {
                $content_manager->registrateOperation($customer,'update','scheduled');
            });

            self::deleting(function($customer)use($content_manager) {
                $content_manager->registrateOperation($customer,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function attributes() {
        return $this->hasMany(Customer_Attribute::class, 'fk_customer_id');
    }

    public function billing_adress() {
        return $this->hasOne(Customer_Billing_Adresses::class, 'fk_customer_id');
    }

    public function shipping_adress() {
        return $this->hasOne(Customer_Shipping_Adresses::class, 'fk_customer_id');
    }

    public function contacts() {
        return $this->hasMany(Customer_Contacts::class, 'fk_customer_id');
    }



    public static function sidenavConfig()
    {
        $Tenant_type = config()->get('tenant.tenant_type');
            if($Tenant_type=='vstcl-industry')
            {
                return [
                    [ 'name' => 'Kundenliste', 'route' => '/customers', 'iconClass' => 'fas fa-table' ],
                    [ 'name' => 'Neuen Kunden anlegen', 'route' => '/customers/new', 'iconClass' => 'fas fa-table' ],
                    [ 'name' => 'Zahlungsbedingungen verwalten', 'route' => '/zahlungsbedingungen', 'iconClass' => 'fas fa-table' ],
                    [ 'name' => 'Preisgruppen verwalten', 'route' => '/pricegroups', 'iconClass' => 'fas fa-table' ]
                ];
            }
            else
            {
                return [
                    [ 'name' => 'Kundenliste', 'route' => '/customers', 'iconClass' => 'fas fa-table' ],
                    [ 'name' => 'Neuen Kunden anlegen', 'route' => '/customers/new', 'iconClass' => 'fas fa-table' ],
                    [ 'name' => 'Zahlungsbedingungen verwalten', 'route' => '/zahlungsbedingungen', 'iconClass' => 'fas fa-table' ]
                ];
            }

    }

    public function pricegroups() {
        return $this->hasMany(Price_Groups_Customers::class, 'customer_id');
    }

    public function payment_conditions() {
        return $this->hasMany(PaymentConditionsCustomers::class, 'fk_customer_id');
    }

    public function customer_article_prices() {
        return $this->hasMany(Price_Customer_Articles::class, 'fk_customer_id');
    }

    public function customer_category_vouchers() {
        return $this->hasMany(Price_Customer_Categories::class, 'fk_customer_id');
    }



}
