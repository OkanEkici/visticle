<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Manager\Content\ContentManager;

class Attribute_Set extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['id','name', 'description', 'main_set'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($attribute_set)use($content_manager) {
                $content_manager->registrateOperation($attribute_set,'insert','scheduled');
            });

            self::updated(function($attribute_set)use($content_manager) {
                $content_manager->registrateOperation($attribute_set,'update','scheduled');
            });

            self::deleting(function($attribute_set)use($content_manager) {
                $content_manager->registrateOperation($attribute_set,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function articles() {
        return $this->hasMany(Article::class, 'fk_attributeset_id');
    }

    public function article_variations() {
        return $this->hasMany(Article_Variation::class, 'fk_attributeset_id');
    }

    public function groups() {
        return $this->belongsToMany(Attribute_Group::class, 'attribute__sets_attribute__groups', 'fk_attributeset_id', 'fk_attributegroup_id');
    }
}
