<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Manager\Content\ContentManager;

class Price_Customer_Articles extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'id'
        ,'rel_type'
        , 'rel_value'
        , 'standard'
        , 'discount'
        , 'fk_customer_id'
        , 'fk_article_id'
        , 'fk_article_variation_id'
    ];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($price_customer_article)use($content_manager) {
                $content_manager->registrateOperation($price_customer_article,'insert','scheduled');
            });

            self::updated(function($price_customer_article)use($content_manager) {
                $content_manager->registrateOperation($price_customer_article,'update','scheduled');
            });

            self::deleting(function($price_customer_article)use($content_manager) {
                $content_manager->registrateOperation($price_customer_article,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }
    public function article() {
        return $this->belongsTo(Article::class, 'fk_article_id');
    }
    public function variation() {
        return $this->belongsTo(Article_Variation::class, 'fk_article_variation_id');
    }
    public function customer() {
        return $this->belongsTo(Customer::class, 'fk_customer_id');
    }

    public function customer_category_vouchers() {
        return $this->hasMany(Price_Customer_Categories::class, 'fk_customer_id');
    }
}
