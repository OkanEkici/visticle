<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Tenant\ProviderController;
use App\Manager\Content\ContentManager;

class Article_Image_Attribute extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_article_image_id','name', 'value'];
    protected $hidden = ['created_at', 'updated_at'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($article_image_attribute)use($content_manager) {
                $content_manager->registrateOperation($article_image_attribute,'insert','scheduled');
            });

            self::updated(function($article_image_attribute)use($content_manager) {
                $content_manager->registrateOperation($article_image_attribute,'update','scheduled');
            });

            self::deleting(function($article_image_attribute)use($content_manager) {
                $content_manager->registrateOperation($article_image_attribute,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function image() {
        return $this->belongsTo(Article_Image::class, 'fk_article_image_id');
    }
}
