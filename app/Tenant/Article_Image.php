<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Tenant\ProviderController;
use App\Manager\Content\ContentManager;

class Article_Image extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_article_id', 'location', 'fashioncloud_id'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($article_image)use($content_manager) {
                $content_manager->registrateOperation($article_image,'insert','scheduled');
            });

            self::updated(function($article_image)use($content_manager) {
                $content_manager->registrateOperation($article_image,'update','scheduled');
            });

            self::deleting(function($article_image)use($content_manager) {
                $content_manager->registrateOperation($article_image,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function article() {
        return $this->belongsTo(Article::class, 'fk_article_id');
    }

    public function attributes() {
        return $this->hasMany(Article_Image_Attribute::class, 'fk_article_image_id');
    }

    public function updateOrCreateAttribute($name, $value){
        return Article_Image_Attribute::updateOrCreate(
        ['fk_article_image_id' => $this->id,'name' => $name],
        ['value' =>  $value]);
    }
}
