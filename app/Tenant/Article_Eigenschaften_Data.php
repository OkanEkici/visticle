<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Tenant\ProviderController;

class Article_Eigenschaften_Data extends Model
{
    protected $connection = 'tenant';
    protected $table = 'article__eigenschaften__data';

    protected $fillable = ['id','name', 'value', 'fk_eigenschaft_id','created_at', 'updated_at','article__eigenschaften__articles','fk_eigenschaft_data_id','fk_article_id', 'fk_variation_id','active'];
    protected $primaryKey = 'id';
    public function eigenschaft() {
        return $this->belongsTo(Article_Eigenschaften::class, 'fk_eigenschaft_id');
    }
    public function articles() {
        return $this->belongsToMany(Article_Eigenschaften_Articles::class, 'article__eigenschaften__articles','fk_eigenschaft_data_id','fk_article_id','id');
    }

}
