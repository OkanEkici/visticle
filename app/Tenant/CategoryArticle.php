<?php

namespace App\Tenant;

use App\Http\Controllers\Tenant\ProviderController;
use Illuminate\Database\Eloquent\Model;
use App\Tenant\Category;
use App\Manager\Content\ContentManager;
use App\Tenant\Article;

class CategoryArticle extends Model
{

    protected $connection = 'tenant';
    protected $table ='category_article';

    protected $fillable = ['article_id', 'category_id', 'value', ];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($category_article)use($content_manager) {
                $content_manager->registrateOperation($category_article,'insert','scheduled');
            });

            self::updated(function($category_article)use($content_manager) {
                $content_manager->registrateOperation($category_article,'update','scheduled');
            });

            self::deleting(function($category_article)use($content_manager) {
                $content_manager->registrateOperation($category_article,'delete','scheduled');
            });
        }
        catch(\Exception $e){

        }
    }
    public function category() {
        return $this->belongsTo(Category::class, 'category_id');
    }
    public function article() {
        return $this->belongsTo(Article::class, 'article_id');
    }
}
