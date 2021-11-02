<?php
namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Manager\Content\ContentManager;

class Price_Groups_Customers extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['id','customer_id', 'group_id', 'standard', 'discount','rel_type'
    , 'rel_value'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($price_group_customer)use($content_manager) {
                $content_manager->registrateOperation($price_group_customer,'insert','scheduled');
            });

            self::updated(function($price_group_customer)use($content_manager) {
                $content_manager->registrateOperation($price_group_customer,'update','scheduled');
            });

            self::deleting(function($price_group_customer)use($content_manager) {
                $content_manager->registrateOperation($price_group_customer,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function customer() {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function pricegroup() {
        return $this->belongsTo(Price_Groups::class, 'group_id');
    }
}
