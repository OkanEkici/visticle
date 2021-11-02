<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Tenant\ProviderController;
use App\Tenant;
use Log;
use App\Manager\Content\ContentManager;

class Attribute_Group extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['name', 'description', 'position','is_filterable','value', 'main_group', 'unit_type', 'active'];


    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($attribute_group)use($content_manager) {
                $content_manager->registrateOperation($attribute_group,'insert','scheduled');
            });

            self::updated(function($attribute_group)use($content_manager) {
                $content_manager->registrateOperation($attribute_group,'update','scheduled');
            });

            self::deleting(function($attribute_group)use($content_manager) {
                $content_manager->registrateOperation($attribute_group,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function article_attributes() {
        return $this->hasMany(Article_Attribute::class, 'fk_attributegroup_id');
    }

    public function variation_attributes() {
        return $this->hasMany(Article_Variation_Attribute::class, 'fk_attributegroup_id');
    }

    public function sets() {
        return $this->belongsToMany(Attribute_Set::class, 'attribute__sets_attribute__groups', 'fk_attributegroup_id', 'fk_attributeset_id');
    }
}
