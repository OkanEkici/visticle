<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Manager\Content\ContentManager;

class Brand extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['name', 'description', 'slug', 'image', 'link', 'meta_title', 'meta_description', 'meta_keywords'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($brand)use($content_manager) {
                $content_manager->registrateOperation($brand,'insert','scheduled');
            });

            self::updated(function($brand)use($content_manager) {
                $content_manager->registrateOperation($brand,'update','scheduled');
            });

            self::deleting(function($brand)use($content_manager) {
                $content_manager->registrateOperation($brand,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function articles() {
        return $this->hasMany(Article::class, 'fk_brand_id');
    }

    public function suppliers() {
        return $this->hasMany(BrandsSuppliers::class, 'fk_brand_id');
    }
}
