<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_provider_type', 'name', 'description', 'url', 'apikey'];

    public function orders() {
        return $this->hasMany(Order::class, 'fk_provider_id');
    }

    public function type() {
        return $this->belongsTo(Provider_Type::class, 'fk_provider_type');
    }

    public function articles() {
        return $this->hasMany(ArticleProvider::class, 'fk_provider_id');
    }

    public function realArticles() {
        return $this->hasManyThrough(Article::class, ArticleProvider::class, 'fk_provider_id', 'id' , null, 'fk_article_id');
    }

    public function categories () {
        return $this->hasMany(CategoryProvider::class, 'fk_provider_id');
    }

    public function realCategories(){
        return $this->belongsToMany(Category::class,'category_providers','fk_provider_id','fk_category_id');
    }

    public function config() {
        return $this->hasMany(Provider_Config::class, 'fk_provider_id');
    }

    /**
     * Liefert einem alle Provider zurück, die über einen Kategoriebaum verfügen.
     * Die Ausgabe ist aufsteigend sortiert nach dem Namen des Providers
     */
    public static function providersWithCategories(){
        $providers=self::query()->whereHas('realCategories')->orderBy('name');

        return $providers;
    }
}
