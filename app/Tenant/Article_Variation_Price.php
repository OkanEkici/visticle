<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Tenant\ProviderController;
use App\Manager\Content\ContentManager;

class Article_Variation_Price extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_article_variation_id', 'name', 'value','batch_nr'];
    protected $hidden = ['created_at', 'updated_at'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($article_variation_price)use($content_manager) {
                $content_manager->registrateOperation($article_variation_price,'insert','scheduled');
            });

            self::updated(function($article_variation_price)use($content_manager) {
                $content_manager->registrateOperation($article_variation_price,'update','scheduled');
            });

            self::deleting(function($article_variation_price)use($content_manager) {
                $content_manager->registrateOperation($article_variation_price,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function variation(){
        return $this->belongsTo(Article_Variation::class, 'fk_article_variation_id');
    }
}
