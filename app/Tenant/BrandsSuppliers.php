<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Manager\Content\ContentManager;

class BrandsSuppliers extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_brand_id', 'hersteller-nr', 'hersteller_name'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($brand_supplier)use($content_manager) {
                $content_manager->registrateOperation($brand_supplier,'insert','scheduled');
            });

            self::updated(function($brand_supplier)use($content_manager) {
                $content_manager->registrateOperation($brand_supplier,'update','scheduled');
            });

            self::deleting(function($brand_supplier)use($content_manager) {
                $content_manager->registrateOperation($brand_supplier,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function brand() {
        return $this->belongsTo(Brand::class, 'fk_brand_id');
    }
}
