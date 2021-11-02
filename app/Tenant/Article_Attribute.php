<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Tenant\ProviderController;
use App\Manager\Content\ContentManager;

class Article_Attribute extends Model
{
    protected $connection = 'tenant';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = ['fk_article_id', 'name', 'value', 'fk_attributegroup_id'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($article_attribute)use($content_manager) {
                $content_manager->registrateOperation($article_attribute,'insert','scheduled');
            });

            self::updated(function($article_attribute)use($content_manager) {
                $content_manager->registrateOperation($article_attribute,'update','scheduled');
            });

            self::deleting(function($article_attribute)use($content_manager) {

                $content_manager->registrateOperation($article_attribute,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function article() {
        return $this->belongsTo(Article::class, 'fk_article_id');
    }

    public function group() {
        return $this->belongsTo(Attribute_Group::class, 'fk_attributegroup_id');
    }
}
