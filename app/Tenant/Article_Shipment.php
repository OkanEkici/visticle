<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Manager\Content\ContentManager;

class Article_Shipment extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_article_id', 'price', 'time', 'description'];
    protected $hidden = ['created_at', 'updated_at'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($article_shipment)use($content_manager) {
                $content_manager->registrateOperation($article_shipment,'insert','scheduled');
            });

            self::updated(function($article_shipment)use($content_manager) {
                $content_manager->registrateOperation($article_shipment,'update','scheduled');
            });

            self::deleting(function($article_shipment)use($content_manager) {
                $content_manager->registrateOperation($article_shipment,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function article() {
        return $this->belongsTo(Article::class, 'fk_article_id');
    }
}
