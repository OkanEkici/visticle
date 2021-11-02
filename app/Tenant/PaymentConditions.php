<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Manager\Content\ContentManager;

class PaymentConditions extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['id', 'name', 'condition'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($payment_condition)use($content_manager) {
                $content_manager->registrateOperation($payment_condition,'insert','scheduled');
            });

            self::updated(function($payment_condition)use($content_manager) {
                $content_manager->registrateOperation($payment_condition,'update','scheduled');
            });

            self::deleting(function($payment_condition)use($content_manager) {
                $content_manager->registrateOperation($payment_condition,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }
    public function customers() {
        return $this->hasMany(PaymentConditionsCustomers::class, 'fk_pcondition_id');
    }
}
