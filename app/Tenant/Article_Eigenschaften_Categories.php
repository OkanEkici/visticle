<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Article_Eigenschaften_Categories extends Model
{
    protected $connection = 'tenant';
    protected $table = 'article__eigenschaften__categories';
    protected $hidden = ['created_at', 'updated_at'];
    protected $primaryKey = 'id';
    protected $fillable = ['id', 'fk_eigenschaft_id', 'fk_category_id','active'];


    public function eigenschaft() {
        return $this->belongsTo(Article_Eigenschaften::class, 'fk_eigenschaft_id');
    }
    public function category() {
        return $this->belongsTo(Category::class, 'fk_category_id');
    }
    

}
