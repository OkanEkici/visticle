<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Tenant\ProviderController;
use App\Manager\Content\ContentManager;

class Article_Price extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_article_id', 'name', 'value','batch_nr'];
    protected $hidden = ['created_at', 'updated_at'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($article_price)use($content_manager) {
                $content_manager->registrateOperation($article_price,'insert','scheduled');
            });

            self::updated(function($article_price)use($content_manager) {
                $content_manager->registrateOperation($article_price,'update','scheduled');
            });

            self::deleting(function($article_price)use($content_manager) {
                $content_manager->registrateOperation($article_price,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }

    public function article() {
        return $this->belongsTo(Article::class, 'fk_article_id');
    }
}
