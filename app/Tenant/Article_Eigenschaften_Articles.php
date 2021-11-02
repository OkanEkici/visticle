<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Tenant\ProviderController;

class Article_Eigenschaften_Articles extends Model
{
    protected $connection = 'tenant';
    protected $table = 'article__eigenschaften__articles';

    protected $fillable = ['id', 'fk_eigenschaft_data_id', 'fk_article_id', 'fk_variation_id','active','created_at', 'updated_at'];
    protected $primaryKey = 'id';

    public function eigenschaft_data() {
        return $this->hasMany(Article_Eigenschaften_Data::class,'fk_eigenschaft_data_id');
    }
    public function article() {
        return $this->belongsTo(Article::class, 'fk_article_id');
    }
    public function variation() {
        return $this->belongsTo(Article_Variation::class, 'fk_variation_id');
    }
    

}
