<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Tenant\ProviderController;
use App\Tenant;
use App\Manager\Content\ContentManager;

class Sparesets extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'id'
        ,'name'
        , 'description'
    ];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($spareset)use($content_manager) {
                $content_manager->registrateOperation($spareset,'insert','scheduled');
            });

            self::updated(function($spareset)use($content_manager) {
                $content_manager->registrateOperation($spareset,'update','scheduled');
            });

            self::deleting(function($spareset)use($content_manager) {
                $content_manager->registrateOperation($spareset,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function articles() {
        return $this->hasMany(Sparesets_Articles::class, 'fk_spareset_id');
    }
    public function categories() {
        return $this->hasMany(Sparesets_Categories::class, 'fk_spareset_id');
    }
    public function spare_articles() {
        return $this->hasMany(Sparesets_SpareArticles::class, 'fk_spareset_id');
    }


}
