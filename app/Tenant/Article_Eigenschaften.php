<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Tenant\ProviderController;

class Article_Eigenschaften extends Model
{
    protected $connection = 'tenant';
    protected $table = 'article__eigenschaften';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = ['id','name', 'is_filterable', 'active', 'sw_id'];

    public function eigenschaften() {
        return $this->hasMany(Article_Eigenschaften_Data::class, 'fk_eigenschaft_id');
    }

    public function categories() {
        return $this->hasMany(Article_Eigenschaften_Categories::class, 'fk_eigenschaft_id');
    }

    

    public function eigenschaft_cats() {
        return $this->belongsToMany(Category::class, 'article__eigenschaften__categories','fk_eigenschaft_id', 'fk_category_id','id','id');
    }

}
