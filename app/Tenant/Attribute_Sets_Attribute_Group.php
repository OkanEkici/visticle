<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Tenant\ProviderController;
use App\Tenant;
use Log;
use App\Manager\Content\ContentManager;

class Attribute_Sets_Attribute_Group extends Model
{
    protected $connection = 'tenant';
    protected $table='attribute__sets_attribute__groups';

    protected $fillable = ['fk_attributeset_id','fk_attributegroup_id'];


    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($attribute_set_attribute_group)use($content_manager) {
                $content_manager->registrateOperation($attribute_set_attribute_group,'insert','scheduled');
            });

            self::updated(function($attribute_set_attribute_group)use($content_manager) {
                $content_manager->registrateOperation($attribute_set_attribute_group,'update','scheduled');
            });

            self::deleting(function($attribute_set_attribute_group)use($content_manager) {
                $content_manager->registrateOperation($attribute_set_attribute_group,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }
    public function set() {
        return $this->belongsTo(Attribute_Set::class,'fk_attributeset_id');
    }
    public function group() {
        return $this->belongsTo(Attribute_Group::class,'fk_attributegroup_id');
    }
}
