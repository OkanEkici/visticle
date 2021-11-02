<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Tenant\ProviderController;
use App\Manager\Content\ContentManager;

class Article_Variation_Image_Attribute extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_article_variation_image_id', 'name', 'value'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($article_variation_image_attribute)use($content_manager) {
                $content_manager->registrateOperation($article_variation_image_attribute,'insert','scheduled');
            });

            self::updated(function($article_variation_image_attribute)use($content_manager) {
                $content_manager->registrateOperation($article_variation_image_attribute,'update','scheduled');
            });

            self::deleting(function($article_variation_image_attribute)use($content_manager) {
                $content_manager->registrateOperation($article_variation_image_attribute,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function image() {
        return $this->belongsTo(Article_Variation_Image::class, 'fk_article_variation_image_id');
    }
}
