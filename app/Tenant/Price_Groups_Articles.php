<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Manager\Content\ContentManager;

class Price_Groups_Articles extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['id','article_id', 'group_id', 'standard', 'discount'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($price_groups_article)use($content_manager) {
                $content_manager->registrateOperation($price_groups_article,'insert','scheduled');
            });

            self::updated(function($price_groups_article)use($content_manager) {
                $content_manager->registrateOperation($price_groups_article,'update','scheduled');
            });

            self::deleting(function($price_groups_article)use($content_manager) {
                $content_manager->registrateOperation($price_groups_article,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }
    public function article() {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function pricegroup() {
        return $this->belongsTo(Price_Groups::class, 'group_id');
    }
}
