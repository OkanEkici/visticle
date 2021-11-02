<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Tenant\ProviderController;
use App\Tenant;
use App\Manager\Content\ContentManager;

class Equipmentsets_Articles extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'id'
        , 'fk_article_id'
        , 'fk_art_var_id'
        , 'fk_eqset_id'
    ];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($equipmentset_article)use($content_manager) {
                $content_manager->registrateOperation($equipmentset_article,'insert','scheduled');
            });

            self::updated(function($equipmentset_article)use($content_manager) {
                $content_manager->registrateOperation($equipmentset_article,'update','scheduled');
            });

            self::deleting(function($equipmentset_article)use($content_manager) {
                $content_manager->registrateOperation($equipmentset_article,'delete','scheduled');
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

    public function equipmentset() {
        return $this->belongsTo(Equipmentsets::class, 'fk_eqset_id');
    }



}
