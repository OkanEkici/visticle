<?php

namespace App\Tenant;

use App\Http\Controllers\Tenant\ProviderController;
use Illuminate\Database\Eloquent\Model;
use App\Manager\Content\ContentManager;

class Article_Variation_Image extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_article_variation_id', 'location', 'loaded'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($article_variation_image)use($content_manager) {
                $content_manager->registrateOperation($article_variation_image,'insert','scheduled');
            });

            self::updated(function($article_variation_image)use($content_manager) {
                $content_manager->registrateOperation($article_variation_image,'update','scheduled');
            });

            self::deleting(function($article_variation_image)use($content_manager) {
                $content_manager->registrateOperation($article_variation_image,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function variation() {
        return $this->belongsTo(Article_Variation::class, 'fk_article_variation_id');
    }

    public function attributes() {
        return $this->hasMany(Article_Variation_Image_Attribute::class, 'fk_article_variation_image_id');
    }

    public function updateOrCreateAttribute($name, $value){
        return Article_Variation_Image_Attribute::updateOrCreate(
        ['fk_article_variation_image_id' => $this->id,'name' => $name],
        ['value' =>  $value]);
    }
}
