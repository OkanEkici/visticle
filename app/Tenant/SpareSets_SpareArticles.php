<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Tenant\ProviderController;
use App\Tenant;
use App\Manager\Content\ContentManager;
class Sparesets_SpareArticles extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'id'
        , 'fk_article_id'
        , 'fk_art_var_id'
        , 'fk_spareset_id'
    ];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($spareset_spare_article)use($content_manager) {
                $content_manager->registrateOperation($spareset_spare_article,'insert','scheduled');
            });

            self::updated(function($spareset_spare_article)use($content_manager) {
                $content_manager->registrateOperation($spareset_spare_article,'update','scheduled');
            });

            self::deleting(function($spareset_spare_article)use($content_manager) {
                $content_manager->registrateOperation($spareset_spare_article,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function article() {
        return $this->belongsTo(Article::class, 'fk_article_id');
    }

    public function variation() {
        return $this->belongsTo(Article_Variation::class, 'fk_art_var_id');
    }

    public function spareset() {
        return $this->belongsTo(Sparesets::class, 'fk_spareset_id');
    }



}
