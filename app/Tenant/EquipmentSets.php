<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Tenant\ProviderController;
use App\Tenant;
use App\Manager\Content\ContentManager;

class Equipmentsets extends Model
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

            self::created(function($equipmentset)use($content_manager) {
                $content_manager->registrateOperation($equipmentset,'insert','scheduled');
            });

            self::updated(function($equipmentset)use($content_manager) {
                $content_manager->registrateOperation($equipmentset,'update','scheduled');
            });

            self::deleting(function($equipmentset)use($content_manager) {
                $content_manager->registrateOperation($equipmentset,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function articles() {
        return $this->hasMany(Equipmentsets_Articles::class, 'fk_eqset_id');
    }
    public function categories() {
        return $this->hasMany(Equipmentsets_Categories::class, 'fk_eqset_id');
    }
    public function equipment_articles() {
        return $this->hasMany(Equipmentsets_EquipmentArticles::class, 'fk_eqset_id');
    }


}
