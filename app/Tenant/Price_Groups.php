<?php

namespace App\Tenant;

use App\Tenant\Price_Groups_Articles;
use App\Tenant\Price_Groups_Categories;
use App\Tenant\Price_Groups_Customers;
use App\Manager\Content\ContentManager;

use Illuminate\Database\Eloquent\Model;

class Price_Groups extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'id'
        ,'name'
        , 'description'
        , 'position'
        , 'val_type'
        , 'value'
        , 'active'
    ];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($price_group)use($content_manager) {
                $content_manager->registrateOperation($price_group,'insert','scheduled');
            });

            self::updated(function($price_group)use($content_manager) {
                $content_manager->registrateOperation($price_group,'update','scheduled');
            });

            self::deleting(function($price_group)use($content_manager) {
                $content_manager->registrateOperation($price_group,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function articles() {
        return $this->hasMany(Price_Groups_Articles::class, 'group_id');
    }
    public function categories() {
        return $this->hasMany(Price_Groups_Categories::class, 'group_id');
    }
    public function customers() {
        return $this->hasMany(Price_Groups_Customers::class, 'group_id');
    }

    public static function sidenavConfig() {
        return [
            [
                'name' => 'Kundenliste',
                'route' => '/customers',
                'iconClass' => 'fas fa-table'
              ],
              [
                  'name' => 'Neuen Kunden anlegen',
                  'route' => '/customers/new',
                  'iconClass' => 'fas fa-table'
              ],
              [
                  'name' => 'Zahlungsbedingungen verwalten',
                  'route' => '/zahlungsbedingungen',
                  'iconClass' => 'fas fa-table'
              ],


            [
              'name' => 'Preisgruppen verwalten',
              'route' => '/pricegroups',
              'iconClass' => 'fas fa-table'
            ],
            /*,
            [
                'name' => 'Artikel-Preisgruppen',
                'route' => '/pricegroups/articles',
                'iconClass' => 'fas fa-table'
            ],
            [
                'name' => 'Kategorie-Preisgruppen',
                'route' => '/pricegroups/categories',
                'iconClass' => 'fas fa-table'
            ],
            [
                'name' => 'Kunden-Preisgruppen',
                'route' => '/pricegroups/customers',
                'iconClass' => 'fas fa-table'
            ]*/
        ];
    }
}
