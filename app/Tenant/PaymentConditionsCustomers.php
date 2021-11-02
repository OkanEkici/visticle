<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Manager\Content\ContentManager;

class PaymentConditionsCustomers extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['id', 'fk_pcondition_id', 'fk_customer_id'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($payment_condition_customer)use($content_manager) {
                $content_manager->registrateOperation($payment_condition_customer,'insert','scheduled');
            });

            self::updated(function($payment_condition_customer)use($content_manager) {
                $content_manager->registrateOperation($payment_condition_customer,'update','scheduled');
            });

            self::deleting(function($payment_condition_customer)use($content_manager) {
                $content_manager->registrateOperation($payment_condition_customer,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function customers() {
        return $this->belongsTo(Customer::class, 'fk_customer_id');
    }
    public function condition() {
        return $this->belongsTo(PaymentCondition::class, 'fk_pcondition_id');
    }
}
