<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Manager\Content\ContentManager;

class Price_Customer_Categories extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'id'
        ,'rel_type'
        , 'rel_value'
        , 'fk_customer_id'
        , 'fk_category_id'
        , 'fk_pricegroup_id'
    ];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($price_customer_category)use($content_manager) {
                $content_manager->registrateOperation($price_customer_category,'insert','scheduled');
            });

            self::updated(function($price_customer_category)use($content_manager) {
                $content_manager->registrateOperation($price_customer_category,'update','scheduled');
            });

            self::deleting(function($price_customer_category)use($content_manager) {
                $content_manager->registrateOperation($price_customer_category,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }
    public function category() {
        return $this->belongsTo(Category::class, 'fk_category_id');
    }
    public function customer() {
        return $this->belongsTo(Customer::class, 'fk_customer_id');
    }
    public function pricegroup() {
        return $this->belongsTo(Price_Groups::class, 'fk_pricegroup_id');
    }
}
